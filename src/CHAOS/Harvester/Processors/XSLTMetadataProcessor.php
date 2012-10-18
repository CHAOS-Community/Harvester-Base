<?php
namespace CHAOS\Harvester\Processors;

use CHAOS\Harvester\Shadows\MetadataShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;
use \XSLTProcessor;
use \DOMDocument;
use \SimpleXMLElement;

class XSLTMetadataProcessor extends MetadataProcessor {
	
	protected $_processor;
	
	public function __construct($harvester, $name, $parameters = array()) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
		if(!key_exists('stylesheet', $parameters)) {
			throw new RuntimeException('An XSLTMetadataProcessor has to have a "stylesheet" parameter.');
		}
		$stylesheet = $parameters['stylesheet'];
		if(!is_readable($stylesheet)) {
			throw new RuntimeException('The "stylesheet" parameter ('.$stylesheet.') of a XSLTMetadataProcessor is not readable.');
		}
		
		$this->_processor = new XSLTProcessor();
		libxml_clear_errors();
		$stylesheetXSL = simplexml_load_file($stylesheet);
		if($stylesheetXSL === false) {
			$errors = array();
			foreach(libxml_get_errors() as $error) {
				$errors[] = sprintf("%s [line %u, column %u]", $error->message, $error->line, $error->column);
			}
			throw new RuntimeException('Cannot load the XSLT stylesheet: '. implode(', ', $errors));
		}
		$success = $this->_processor->importStylesheet($stylesheetXSL);
		if($success === false) {
			throw new RuntimeException("Couldn't import stylesheet.");
		}
	}
	
	public function generateMetadata($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is generating metadata.");
		
		if($externalObject instanceof DOMDocument) {
			$dom = $externalObject;
		} elseif($externalObject instanceof SimpleXMLElement) {
			$dom = dom_import_simplexml($externalObject)->ownerDocument;
		} else {
			throw new RuntimeException("Cannot generate XMSL metadata from an external object of type ".get_class($externalObject));
		}
		
		$this->_processor->registerPHPFunctions();
		if(is_array($shadow->extras)) {
			foreach($shadow->extras as $key => $value) {
				if(is_string($value) || is_numeric($value)) {
					$success = $this->_processor->setParameter('', $key, $value);
				}
			}
		}
		$result = $this->_processor->transformToDoc($dom);
		return simplexml_import_dom($result);
	}
	
}