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
		
		if($this->unpublishEverywhere) {
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
}