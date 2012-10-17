<?php
namespace CHAOS\Harvester\Shadows;
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
	 * The chaos object from the service.
	 * @var \stdClass Chaos object.
	 */
	protected $object;
	
	/**
	 * Get the chaos object that this shadow represents.
	 * @param unknown_type $harvester The Chaos Harvester used to create the shadow.
	 * @return \stdClass The Chaos object.
	 */
	public function getObject($harvester) {
		if($this->object == null) {
			$this->object = $this->getOrCreate($harvester);
		}
		return $this->object;
	}
	
	public function commit($harvester, $parent = null) {
		$harvester->debug("Committing the shadow of an object.");
		if($parent != null) {
			throw new RuntimeException('Committing related objects has not yet been implemented.');
		}
		
		// Get or create the object, while saving it to the object itself.
		$this->getObject($harvester);
		
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
		// This is sat by the call to getObject.
		return $this->object;
	}
	
	/**
	 * Get or create the object shadow.
	 * @param CHAOS\Harvester\ChaosHarvester $harvester
	 */
	public function getOrCreate($harvester) {
		$chaos = $harvester->getChaosClient();
		// TODO: Consider sorting by DateCreated.
		$response = $chaos->Object()->Get($this->query, 'DateCreated+desc', null, 0, 1, true, true, true);
		if(!$response->WasSuccess()) {
			throw new RuntimeException("General error when getting the object from the chaos service: " . $response->Error()->Message());
		} elseif(!$response->MCM()->WasSuccess()) {
			throw new RuntimeException("MCM error when getting the object from the chaos service: " . $response->MCM()->Error()->Message());
		}
		$object = null;
		if($response->MCM()->TotalCount() == 0) {
			$response = $chaos->Object()->Create($this->objectTypeId, $this->folderId);
			if(!$response->WasSuccess()) {
				throw new RuntimeException("General error when creating the object in the chaos service: " . $response->Error()->Message());
			} elseif(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("MCM error when creating the object in the chaos service: " . $response->MCM()->Error()->Message());
			}
			if($response->MCM()->TotalCount() == 1) {
				$results = $response->MCM()->Results();
				$object = $results[0];
			} else {
				throw new RuntimeException("The service didn't respond with a single object when creating it.");
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
		return $object;
	}
}