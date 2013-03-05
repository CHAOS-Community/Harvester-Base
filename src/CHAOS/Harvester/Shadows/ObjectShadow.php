<?php
namespace CHAOS\Harvester\Shadows;
use \RuntimeException;
class ObjectShadow extends Shadow {
	
	/**
	 * Shadows of the related metadata.
	 * @var MetadataShadow[]
	 */
	public $metadataShadows = array();
	
	/**
	 * Shadows of the related files.
	 * @var FileShadow[]
	 */
	public $fileShadows = array();
	
	/**
	 * Shadows of the related objects.
	 * @var ObjectShadow[]
	 */
	public $relatedObjectShadows = array();
	
	public $folderId;
	
	public $objectTypeId;
	
	/**
	 * The query to execute to get the object from the service.
	 * @var string
	 */
	public $query;
	
	/**
	 * An associative array of extra information exchanged between the different processors
	 * building up the shadow.
	 * @var string[string]
	 */
	public $extras = array();
	
	/**
	 * An array of accesspoint GUIDs on which to publish the object if it should not be skipped.
	 * @var string[]
	 */
	public $publishAccesspointGUIDs = array();
	
	/**
	 * An array of accesspoint GUIDs on which to unpublish objects if it should be skipped.
	 * @var string[]
	 */
	public $unpublishAccesspointGUIDs = array();
	
	/**
	 * If the object is skipped, it is unpublished from any accesspoint.
	 * @var boolean
	 */
	public $unpublishEverywhere;
	
	/**
	 * The chaos object from the service.
	 * @var \stdClass Chaos object.
	 */
	protected $object;
	
	public function commit($harvester, $parent = null) {
		$harvester->debug("Committing the shadow of an object.");
		if($parent != null) {
			throw new RuntimeException('Committing related objects has not yet been implemented.');
		}
		
		if($harvester->hasOption('no-shadow-commitment')) {
			$this->get($harvester, false);
			if($this->object) {
				$harvester->info("Because the 'no-shadow-commitment' runtime option is set, this object is not committed to CHAOS object '%s'.", $this->object->GUID);
				return $this->object;
			} else {
				$harvester->info("Because the 'no-shadow-commitment' runtime option is set, this object is created as a CHAOS object.");
				return;
			}
		}
		
		if($harvester->hasOption('require-files-on-objects') && count($this->fileShadows) == 0) {
			$harvester->info("Object shadow skipped because 'require-files-on-objects' is set and no file shadows was attached.");
			$this->skipped = true;
		}
		
		// Get or create the object, while saving it to the object itself.
		if($this->skipped) {
			// Get the chaos object, but do not create it if its not there.
			$this->get($harvester, false);
		} else {
			$this->get($harvester);
		
			foreach($this->metadataShadows as $metadataShadow) {
				$metadataShadow->commit($harvester, $this);
			}
			
			$fileLine = "Committing files: ";
			foreach($this->fileShadows as $fileShadow) {
				$file = $fileShadow->commit($harvester, $this);
				if($file->status == 'reused') {
					$fileLine .= '.';
				} else if($file->status == 'created') {
					$fileLine .= '+';
				} else {
					$fileLine .= '?';
				}
			}
			
			$fileIDs = array();
			foreach($this->fileShadows as $fileShadow) {
				$fileIDs[] = $fileShadow->getFileID();
			}
			foreach($this->object->Files as $file) {
				if(!in_array($file->ID, $fileIDs)) {
					// This file is related to the object, but it has been removed.
					$harvester->debug("Deleting file #%u.", $file->ID);
					$fileLine .= '-';
					$harvester->getChaosClient()->File()->Delete($file->ID);
				}
			}
			
			/*
			// FIXME: Consider deleting unsued files, ie. files that is related to a reused CHAOS object but which are not in the shadows.
			if(count($this->object->Files) > count($this->fileShadows)) {
				$harvester->info("The reused CHAOS object has more files referenced than the object shadow has. But as the CHAOS client has not implemented a File/Delete call this cannot be completed.");
				
			}
			*/
			
			$harvester->info($fileLine);
			
			foreach($this->relatedObjectShadows as $relatedObjectShadow) {
				// TODO: Consider adding a list of committed object shadows to prevent cycles.
				$relatedObjectShadow->commit($harvester, $this);
			}
		}
		
		$start = new \DateTime();
		// Publish this as of yesterday - servertime issues.
		$aDayInterval = new \DateInterval("P1D");
		$start->sub($aDayInterval);
		
		foreach($this->publishAccesspointGUIDs as $accesspointGUID) {
			$harvester->info(sprintf("Publishing to accesspoint = %s with startDate = %s", $accesspointGUID, $start->format("Y-m-d H:i:s")));
			$response = $harvester->getChaosClient()->Object()->SetPublishSettings($this->object->GUID, $accesspointGUID, $start);
			if(!$response->WasSuccess()) {
				throw new RuntimeException("Couldn't set publish settings: {$response->Error()->Message()}");
			}
			if(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("Couldn't set publish settings: (MCM) {$response->MCM()->Error()->Message()}");
			}
		}
		
		// Only do this if an object was returned from the query.
		if($this->object !== null) {
			if($this->unpublishEverywhere) {
				// TODO: This has actually been implemented now, it could be fixed.
				throw new RuntimeException("Unpublish everywhere is not supported at this moment, as the CHAOS service does not support listing the accesspoints to which the object is published.");
			} else {
				foreach($this->unpublishAccesspointGUIDs as $accesspointGUID) {
					$harvester->info(sprintf("Unpublishing from accesspoint = %s", $accesspointGUID));
					$response = $harvester->getChaosClient()->Object()->SetPublishSettings($this->object->GUID, $accesspointGUID);
					if(!$response->WasSuccess()) {
						throw new RuntimeException("Couldn't set publish settings: {$response->Error()->Message()}");
					}
					if(!$response->MCM()->WasSuccess()) {
						throw new RuntimeException("Couldn't set publish settings: (MCM) {$response->MCM()->Error()->Message()}");
					}
				}
			}
		} else {
			$harvester->info("No need to unpublish as this external object is not represented in CHAOS.");
		}
		
		// This is sat by the call to get.
		return $this->object;
	}
	
	/**
	 * Get or create the object shadow.
	 * @param CHAOS\Harvester\ChaosHarvester $harvester
	 */
	public function get($harvester, $orCreate = true) {
		if($this->object != null) {
			return $this->object;
		}
		
		$chaos = $harvester->getChaosClient();
		
		// TODO: Consider sorting by DateCreated.
		$harvester->debug("Trying to get the CHAOS object from ".$this->query);
		$response = $chaos->Object()->Get($this->query, 'DateCreated+desc', null, 0, 1, true, true, true, true);
		if(!$response->WasSuccess()) {
			throw new RuntimeException("General error when getting the object from the chaos service: " . $response->Error()->Message());
		} elseif(!$response->MCM()->WasSuccess()) {
			throw new RuntimeException("MCM error when getting the object from the chaos service: " . $response->MCM()->Error()->Message());
		}
		$object = null;
		if($response->MCM()->TotalCount() == 0) {
			if($orCreate) {
				$response = $chaos->Object()->Create($this->objectTypeId, $this->folderId);
				if(!$response->WasSuccess()) {
					throw new RuntimeException("General error when creating the object in the chaos service: " . $response->Error()->Message());
				} elseif(!$response->MCM()->WasSuccess()) {
					throw new RuntimeException("MCM error when creating the object in the chaos service: " . $response->MCM()->Error()->Message());
				}
				if($response->MCM()->TotalCount() == 1) {
					$results = $response->MCM()->Results();
					$object = $results[0];
					$harvester->info("Created a new object in the service with GUID = %s.", $object->GUID);
				} else {
					throw new RuntimeException("The service didn't respond with a single object when creating it.");
				}
			} else {
				return null;
			}
		} else {
			if($response->MCM()->TotalCount() > 1) {
				trigger_error('The query specified when getting an object resulted in '.$response->MCM()->TotalCount().' objects. Consider if the query should be more specific.', E_USER_WARNING);
			}
			$results = $response->MCM()->Results();
			$object = $results[0];
			$dateCreated = $object->DateCreated;
			$harvester->info("Reusing object from service, created %s with GUID = %s.", date('r', $dateCreated), $object->GUID);
		}
		
		$this->object = $object;
		return $this->object;
	}
	
	/**
	 * Generates a string representation of the object shadow.
	 * @return string A string representation of the object shadow.
	 */
	public function __toString() {
		if($this->object != null && strlen($this->object->GUID) > 0) {
			return strval($this->object->GUID);
		} elseif(strlen($this->query) > 0) {
			return "[chaos object found from {$this->query}]";
		} else {
			return '';
		}
	}
}