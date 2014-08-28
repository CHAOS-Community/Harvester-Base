<?php
namespace CHAOS\Harvester\DKA\Filters;

class UnpublishedByCuratorFilter extends \CHAOS\Harvester\Filters\Filter {
	public function __construct($harvester, $name, $parameters = array()) {
		parent::__construct($harvester, $name, $parameters);
	} 	

	public function passes($externalObject, $objectShadow) {
		$object = $objectShadow->get($this->_harvester);

		if (!empty($object) && !empty($object->Metadatas)) {
			var_dump('test');
			$metadataSchemes = $object->Metadatas;
			
			foreach ($metadataSchemes as $metadata) {

				// DKA2 metadata scheme.
				if (strtolower($metadata->MetadataSchemaGUID) === '5906a41b-feae-48db-bfb7-714b3e105396') {
					$xml = new \SimpleXMLElement($metadata->MetadataXML);
					if (strval($xml->unpublishedByCurator) === 'true') {
						return false;
					}
					break;
				}
			}
		}

		return true;
	}
}

?>