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
	
	public function commit($harvester) {
		$harvester->debug("Committing the shadow of an object.");
		// TODO: Commit this particular object shadow ...
		
		foreach($this->metadataShadows as $metadataShadow) {
			$metadataShadow->commit($harvester);
		}
		foreach($this->fileShadows as $fileShadow) {
			$fileShadow->commit($harvester);
		}
		foreach($this->relatedObjectShadows as $relatedObjectShadow) {
			// TODO: Consider adding a list of committed object shadows to prevent cycles.
			$relatedObjectShadow->commit($harvester);
		}
	}
}