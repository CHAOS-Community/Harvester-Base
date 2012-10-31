<?php
namespace CHAOS\Harvester\Processors;

use CHAOS\Harvester\Shadows\MetadataShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;
use \Exception;

abstract class MetadataProcessor extends Processor {
	
	protected $_schemaSource;
	protected $_schemaGUID;
	
	public function fetchSchema($schemaGUID, $schemaLocation = null) {
		if($schemaLocation != null) {
			$this->_harvester->debug("Fetching schema: %s (%s)", $schemaGUID, $schemaLocation);
		} else {
			$this->_harvester->debug("Fetching schema: %s", $schemaGUID);
		}
		$this->_schemaGUID = $schemaGUID;
		
		if($schemaLocation == null) {
			$response = $this->_harvester->getChaosClient()->MetadataSchema()->Get($this->_schemaGUID);
			if(!$response->WasSuccess() || !$response->MCM()->WasSuccess() || $response->MCM()->Count() < 1) {
				if(!$response->WasSuccess()) {
					$message = $response->Error()->Message();
				} elseif(!$response->MCM()->WasSuccess()) {
					$message = "(MCM) ".$response->MCM()->Error()->Message();
				} else {
					$message = "No message ..";
				}
				throw new RuntimeException("Failed to fetch XML schemas from the Chaos system, for schema GUID '$this->_schemaGUID': ".$message);
			}
			$schemas = $response->MCM()->Results();
			$this->_schemaSource = $schemas[0]->SchemaXML;
		} else {
			if(strstr($schemaLocation, 'http://') === 0 || strstr($schemaLocation, 'https://') === 0) {
				$this->_schemaSource = file_get_contents($schemaLocation);
			} else {
				$schemaLocation = $this->_harvester->resolvePath($schemaLocation);
				if($schemaLocation == null) {
					throw new RuntimeException("Schema ($schemaLocation) is unreadable.");
				} else {
					$this->_schemaSource = file_get_contents($schemaLocation);
				}
			}
		}
	}
	
	protected $_validate;
	
	public function setValidate($validate) {
		$this->_validate = $validate;
	}
	
	public function process($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is processing.");
		
		assert($shadow instanceof ObjectShadow);
	
		$metadata = new MetadataShadow();
		$metadata->metadataSchemaGUID = $this->_schemaGUID;
		timed();
		$metadata->xml = $this->generateMetadata($externalObject, $shadow);
		timed('generating-metadata');
		if($metadata->xml == null) {
			throw new Exception("An error occured when generating the metadata, check your implementation.");
		}
		if($this->_validate === true) {
			timed();
			$dom = dom_import_simplexml($metadata->xml)->ownerDocument;
			$dom->formatOutput = true;
			if($dom->schemaValidateSource($this->_schemaSource) === false) {
				throw new RuntimeException("The generated metadata didn't match the schema.");
			}
			timed('validating-metadata');
		}
		$shadow->metadataShadows[] = $metadata;
		return $shadow;
	}
	
	public abstract function generateMetadata($externalObject, $shadow = null);
	
}