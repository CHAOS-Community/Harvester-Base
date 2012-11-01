<?php
namespace CHAOS\Harvester\Shadows;
class MetadataShadow extends Shadow {
	
	/**
	 * The XML on the metadata shadow object.
	 * @var \SimpleXMLElement
	 */
	public $xml;
	
	public $metadataSchemaGUID;
	
	public $languageCode = 'da';
	
	public function commit($harvester, $parent = null) {
		$harvester->debug("Committing the shadow of some metadata.");
		if($parent == null || !$parent instanceof ObjectShadow) {
			trigger_error('The shadow given as $parent argument has to be initialized and of type Object Shadow');
		}
		$object = $parent->get($harvester);
		$metadata = array_filter($object->Metadatas, array($this, 'matchMetadataSchema'));
		$revisionID = null;
		if(count($metadata) == 1) {
			$metadata = array_pop($metadata);
			$revisionID = $metadata->RevisionID;
		} elseif(count($metadata) > 1) {
			$metadata = array_pop($metadata);
			trigger_error('It looks like the chaos object has more than one metadata of the specified schema (SchemaGUID = ' .$this->metadataSchemaGUID. ').', E_USER_WARNING);
			$revisionID = $metadata->RevisionID;
		}
		
		$xmlString = $this->xml->saveXML();
		$response = $harvester->getChaosClient()->Metadata()->Set($object->GUID, $this->metadataSchemaGUID, $this->languageCode, $revisionID, $xmlString);
		if(!$response->WasSuccess()) {
			throw new RuntimeException('General error setting metadata for schema GUID = '.$this->metadataSchemaGUID . ': '.$response->Error()->Message());
		}
		if(!$response->MCM()->WasSuccess()) {
			throw new RuntimeException('MCM error setting metadata for schema GUID = '.$this->metadataSchemaGUID . ': '.$response->MCM()->Error()->Message());
		}
	}
	
	protected function matchMetadataSchema($metadata) {
		return $metadata->MetadataSchemaGUID == $this->metadataSchemaGUID;
	}
}