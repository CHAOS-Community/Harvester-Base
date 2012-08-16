<?php
use \XSLTProcessor;
class XSLTMetadataGenerator extends AChaosMetadataGenerator {
	// Only known at runtime.
	//const SCHEMA_NAME = 'DKA2';
	//const SCHEMA_GUID = '5906a41b-feae-48db-bfb7-714b3e105396';
	
	/**
	 * The GUID of the schema on the Chaos service.
	 * @var string
	 */
	protected $_schemaGUID;
	
	/**
	 * The filename of the stylesheet to use for transformations.
	 * @var string
	 */
	protected $_stylesheet;
	
	/**
	 * The processor to use when transforming XML.
	 * @var XSLTProcessor
	 */
	protected $_processor;
	
	const VALIDATE = false;
		
	public function __construct($stylesheet) {
		$this->_stylesheet = $stylesheet;
		// Check that this is infact a valid XSLT stylesheet.
		/*if(!class_exists('\XSLTProcessor', true)) {
			throw new RuntimeException("The XSLTProcessor class does not exsist, do you have the PHP XSL-lib installed? See: http://dk.php.net/manual/en/xsl.setup.php");
		}*/
		
		$this->_processor = new XSLTProcessor();
		if(!file_exists($this->_stylesheet)) {
			throw new RuntimeException('Cannot locate the XSLT stylesheet: '. $this->_stylesheet);
		}
		libxml_clear_errors();
		$stylesheetXSL = simplexml_load_file($this->_stylesheet);
		if($stylesheetXSL === false) {
			$errors = array();
			foreach(libxml_get_errors() as $error) {
				$errors[] = sprintf("%s [line %u, column %u]", $error->message, $error->line, $error->column);
			}
			throw new RuntimeException('Cannot load the XSLT stylesheet: '. implode(', ', $errors));
		}
		$this->_processor->importStylesheet($stylesheetXSL);
	}
	
	/**
	 * Sets the schema source fetching it from a chaos system.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient
	 */
	public function fetchSchema($chaosClient) {
		return $this->fetchSchemaFromGUID($chaosClient, $this->_schemaGUID);
	}
	
	/**
	 * Generate XML from some import-specific object.
	 * @param unknown_type $externalObject
	 * @param boolean $validate Validate the generated XML agains a schema.
	 * @return DOMDocument Representing the imported item as XML in a specific schema.
	 */
	public function generateXML($externalObject, &$extras = array()) {
		if($externalObject instanceof DOMDocument) {
			$dom = $externalObject;
		} elseif($externalObject instanceof SimpleXMLElement) {
			$dom = dom_import_simplexml($externalObject)->ownerDocument;
		}
		$this->_processor->registerPHPFunctions();
		foreach($extras as $key => $value) {
			$this->_processor->setParameter('extras', $key, $value);
		}
		$result = $this->_processor->transformToDoc($dom);
		$result->formatOutput = true;
		
		if(self::VALIDATE) {
			$this->validate($dom);
		}
		return $result;
	}
}