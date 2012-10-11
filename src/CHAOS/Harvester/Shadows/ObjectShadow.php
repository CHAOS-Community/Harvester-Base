<?php
namespace CHAOS\Harvester\Shadows;
class ObjectShadow extends Shadow {
	
	/**
	 * Shadows of the related metadata.
	 * @var MetadataShadow[]
	 */
	public $metadataShadows;
	
	/**
	 * Shadows of the related files.
	 * @var FileShadow[]
	 */
	public $fileShadows;
	
	/**
	 * Shadows of the related objects.
	 * @var ObjectShadow[]
	 */
	public $relatedObjectShadows;
	
	public function commit($harvester) {
		
	}
}