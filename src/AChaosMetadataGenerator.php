<?php
abstract class AChaosMetadataGenerator {
	
	/**
	 * Generate XML from some import-specific object.
	 * @param unknown_type $input
	 * @param boolean $validate Validate the generated XML agains a schema.
	 * @return DOMDocument Representing the imported item as XML in a specific schema.
	 */
	public abstract function generateXML($externalObject, &$extras);
	
	/**
	 * Validates a DOM document using the loaded schema.
	 * @param DOMDocument $document Document to validate.
	 */
	public function validate($document) {
		return $document->schemaValidateSource($this->_schemaSource);
	}
	
	public $_schemaSource;
	
	public function setSchemaSource($source) {
		$this->_schemaSource = $source;
	}
	
	/**
	 * Sets the schema source fetching it from a chaos system.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient
	 * @param string $schemaGUID
	 */
	public function fetchSchemaFromGUID($chaosClient, $schemaGUID) {
		$response = $chaosClient->MetadataSchema()->Get($schemaGUID);
		if(!$response->WasSuccess() || !$response->MCM()->WasSuccess() || $response->MCM()->Count() < 1) {
			throw new RuntimeException("Failed to fetch XML schemas from the Chaos system, for schema GUID '$schemaGUID'.");
		}
		$schemas = $response->MCM()->Results();
		$this->setSchemaSource($schemas[0]->SchemaXML);
	}
	
	/**
	 * Sets the schema source fetching it from a chaos system.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient
	 */
	abstract public function fetchSchema($chaosClient);
	
}