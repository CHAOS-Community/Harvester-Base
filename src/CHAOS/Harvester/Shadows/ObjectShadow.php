<?php
namespace CHAOS\Harvester\Shadows;
use \RuntimeException;
class ObjectShadow extends Shadow {
	
	/**
	 * Number of objects to be considered duplicates.
	 * If more than this number of objects are returned from an object/get,
	 * none of them are considered duplicates as the query are way too
	 * ambiguous.
	 * @var integer
	 */
	const DUPLICATE_OBJECTS_THESHOLD = 100;
	
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
	 * The query (or an array of prioritized queries) to execute to get the object from the service.
	 * @var string|string[]
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
	
	/**
	 * These are the array of objects returned from the service, when the query is too ambiguous.
	 * @var \stdClass[] Chaos Objects.
	 */
	protected $duplicateObjects = array();
	
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
		
		
		if($this->skipped !== true) {
			// Get or create the object, while saving it to the object itself.
			$this->ensureChaosObject($harvester);

			foreach($this->metadataShadows as $metadataShadow) {
				assert($metadataShadow instanceof MetadataShadow);
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
			
			$harvester->info($fileLine);
			
			foreach($this->relatedObjectShadows as $relatedObjectShadow) {
				// TODO: Consider adding a list of committed object shadows to prevent cycles.
				$relatedObjectShadow->commit($harvester, $this);
			}
			$this->publishObject($harvester);
		} else {
			// Only do this if an object was returned from the query.
			if($this->object !== null) {
				self::unpublishObject($harvester, $this->object, $this->unpublishEverywhere, $this->unpublishAccesspointGUIDs);
			} else {
				$harvester->info("No need to unpublish as this external object is not represented in CHAOS.");
			}
		}

		// Unpublish any duplicate objects.
		foreach($this->duplicateObjects as $duplicateObject) {
			self::unpublishObject($harvester, $duplicateObject, true, $this->unpublishAccesspointGUIDs);
		}
		
		// This is sat by the call to get.
		return $this->object;
	}
	
	/**
	 * Publish the object on the accesspoints given in the configuration.
	 * @param CHAOS\Harvester\ChaosHarvester $harvester The harvester used to publish object. Get the chaos client from this.
	 * @param \stdClass|null $object Chaos object to publish, if null use $this->object.
	 * @throws RuntimeException If an error occures while publishing.
	 */
	protected function publishObject($harvester, $object = null) {
		if($object === null) {
			$object = $this->object;
		}
		$start = new \DateTime();
		// Publish this as of yesterday - servertime issues.
		$aDayInterval = new \DateInterval("P1D");
		$start->sub($aDayInterval);
		
		foreach($this->publishAccesspointGUIDs as $accesspointGUID) {
			// Check if the object is published on the accesspoint.
			if(self::isPublished($object, $accesspointGUID) === false) {
				$harvester->info(sprintf("Publishing %s to accesspoint = %s with startDate = %s", $object->GUID, $accesspointGUID, $start->format("Y-m-d H:i:s")));
				$response = $harvester->getChaosClient()->Object()->SetPublishSettings($object->GUID, $accesspointGUID, $start);
				if(!$response->WasSuccess()) {
					throw new RuntimeException("Couldn't set publish settings: {$response->Error()->Message()}");
				}
				if(!$response->MCM()->WasSuccess()) {
					throw new RuntimeException("Couldn't set publish settings: (MCM) {$response->MCM()->Error()->Message()}");
				}
			} else {
				$harvester->debug(sprintf("Skipping the publishing of %s from accesspoint = %s: It's allready published there.", $object->GUID, $accesspointGUID));
			}
		}
		$harvester->objectsConsidered($object);
	}
	
	/**
	 * Unpublish the object on the accesspoints given in the configuration.
	 * @param CHAOS\Harvester\ChaosHarvester $harvester The harvester used to unpublish object. Get the chaos client from this.
	 * @param \stdClass|null $object Chaos object to unpublish, if null use $this->object.
	 * @throws RuntimeException If an error occures while publishing.
	 */
	public static function unpublishObject($harvester, $object = null, $unpublishEverywhere = true, $unpublishAccesspointGUIDs = array()) {
		foreach($unpublishAccesspointGUIDs as &$accesspoint) {
			// Make sure they are all lower-cased.
			$accesspoint = strtolower($accesspoint);
		}
		
		// If unpublish everywhere is set, loop through the accesspoints assoiciated with the object.
		if($unpublishEverywhere) {
			foreach($object->AccessPoints as $accesspoint) {
				$accesspoint_guid = strtolower($accesspoint->AccessPointGUID);
				if(in_array($accesspoint_guid, $unpublishAccesspointGUIDs) === false) {
					// This is a new one to unpublish from.
					$unpublishAccesspointGUIDs[] = $accesspoint_guid;
				}
			}
		}
		
		foreach($unpublishAccesspointGUIDs as $accesspointGUID) {
			// Check if the object is published on the accesspoint.
			if(self::isPublished($object, $accesspointGUID)) {
				$harvester->info(sprintf("Unpublishing %s from accesspoint = %s", $object->GUID, $accesspointGUID));
				$response = $harvester->getChaosClient()->Object()->SetPublishSettings($object->GUID, $accesspointGUID);
				if(!$response->WasSuccess()) {
					throw new RuntimeException("Couldn't set publish settings: {$response->Error()->Message()}");
				}
				if(!$response->MCM()->WasSuccess()) {
					throw new RuntimeException("Couldn't set publish settings: (MCM) {$response->MCM()->Error()->Message()}");
				}
			} else {
				$harvester->debug(sprintf("Skipping the unpublishing of %s from accesspoint = %s: It's not published there anyway.", $object->GUID, is_string($accesspointGUID) ? $accesspointGUID : '\'NULL\''));
			}
		}
		$harvester->objectsConsidered($object);
	}
	
	/**
	 * Get or create the object shadow.
	 * @param \CHAOS\Harvester\ChaosHarvester $harvester
	 */
	public function get($harvester) {
		assert($harvester instanceof \CHAOS\Harvester\ChaosHarvester);
		
		if($this->object != null) {
			return $this->object;
		}
		
		$this->duplicateObjects = array();
		
		$chaos = $harvester->getChaosClient();
		
		if(is_string($this->query)) {
			$this->query = array($this->query);
		}
		
		$object = null;
		$query_problems = array();
		foreach($this->query as $query) {
			
			$harvester->debug("Trying to get the CHAOS object from $query");
			$response = $chaos->Object()->Get($query, 'DateCreated+asc', null, 0, self::DUPLICATE_OBJECTS_THESHOLD+1, true, true, true, true);
			if(!$response->WasSuccess()) {
				throw new RuntimeException("General error when getting the object from the chaos service: " . $response->Error()->Message());
			} elseif(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("MCM error when getting the object from the chaos service: " . $response->MCM()->Error()->Message());
			}
			
			if($response->MCM()->TotalCount() > 1) {
				trigger_error('The query specified when getting an object resulted in '.$response->MCM()->TotalCount().' objects. Consider if the query should be more specific.', E_USER_WARNING);
				if($response->MCM()->TotalCount()-1 > self::DUPLICATE_OBJECTS_THESHOLD) {
					$query_problems[] = strval($response->MCM()->TotalCount()-1)." duplicate objects, is too many (> ".strval(self::DUPLICATE_OBJECTS_THESHOLD)."). The query is way too ambiguous.";
					continue; // Skip this query!
				}
			} elseif($response->MCM()->TotalCount() == 0) {
				continue; // Skip this query - nothing returned.
			}
			
			$results = $response->MCM()->Results();
			$object = $results[0];
			foreach($results as $duplicateObject) {
				if($duplicateObject != $object) {
					$this->duplicateObjects[] = $duplicateObject;
				}
			}
			$dateCreated = $object->DateCreated;
			$harvester->info("Reusing object from service, created %s with GUID = %s.", date('r', $dateCreated), $object->GUID);
			break;
		}
		
		$this->object = $object;
		return $this->object;
	}

	/**
	 * Ensure chaos object create
	 * @param \CHAOS\Harvester\ChaosHarvester $harvester
	 */
	public function ensureChaosObject($harvester) {
		assert($harvester instanceof \CHAOS\Harvester\ChaosHarvester);
		
		if($this->object != null) {
			return $this->object;
		}
		
		$chaos = $harvester->getChaosClient();
		
		if($this->get($harvester) == null) {
			$response = $chaos->Object()->Create($this->objectTypeId, $this->folderId);
			if(!$response->WasSuccess()) {
				throw new RuntimeException("General error when creating the object in the chaos service: " . $response->Error()->Message());
			} elseif(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("MCM error when creating the object in the chaos service: " . $response->MCM()->Error()->Message());
			}
			if($response->MCM()->TotalCount() == 1) {
				$results = $response->MCM()->Results();
				$this->object = $results[0];
				return $this->object;
				$harvester->info("Created a new object in the service with GUID = %s.", $object->GUID);
			} else {
				throw new RuntimeException("The service didn't respond with a single object when creating it.");
			}
		}
	}
	
	/**
	 * Checks from the start and end-dates of the accesspoints of an object, if its published or not.
	 * @param \stdClass $chaos_object The CHAOS object to check - this should be fetched from the CHAOS service with the includeAccesspoints option set to true.
	 * @param string|null $accesspoint_guid The Accesspoint GUID to check if the object is published to. (optional) If null, any accesspoint will be considered.
	 */
	public static function isPublished($chaos_object, $accesspoint_guid = null) {
		$now = new \DateTime();
		foreach($chaos_object->AccessPoints as $accesspoint) {
			if($accesspoint_guid === null || (is_string($accesspoint_guid) && strtolower($accesspoint_guid) === strtolower($accesspoint->AccessPointGUID))) {
				// Check the start and end dates.
				if($accesspoint->StartDate == null) {
					continue; // Skipping something which has no start date set.
				}
				$startDate = new \DateTime();
				$startDate->setTimestamp($accesspoint->StartDate);
				// Is now after the start date?
				if($startDate < $now) {
					// Is the end date not sat? I.e. is it at the end of our time?
					if($accesspoint->EndDate == null) {
						return true;
					} else {
						$endDate = new \DateTime();
						$endDate->setTimestamp($accesspoint->EndDate);
						// Are we still publishing this?
						if($now < $endDate) {
							return true;
						}
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * Generates a string representation of the object shadow.
	 * @return string A string representation of the object shadow.
	 */
	public function __toString() {
		if($this->object != null && strlen($this->object->GUID) > 0) {
			return strval($this->object->GUID);
		} elseif(is_string($this->query) && strlen($this->query) > 0) {
			return "[chaos object found from {$this->query}]";
		} elseif(is_array($this->query) && count($this->query) > 0) {
			return "[chaos object found from ". implode(' OR ', $this->query) ."]";
		} else {
			return '';
		}
	}
}
