<?php
namespace CHAOS\Harvester\Shadows;
class MetadataShadow extends Shadow {
	
	/**
	 * The XML on the metadata shadow object.
	 * @var \SimpleXMLElement;
	 */
	public $xml;
	
	public function commit($harvester) {
		$harvester->debug("Committing the shadow of some metadata.");
	}
}