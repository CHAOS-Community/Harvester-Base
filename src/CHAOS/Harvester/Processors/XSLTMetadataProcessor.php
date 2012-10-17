<?php
namespace CHAOS\Harvester\Processors;

use CHAOS\Harvester\Shadows\MetadataShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

class XSLTMetadataProcessor extends MetadataProcessor {
	
	protected $stylesheet;
	
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
		
		// TODO: Load the stylesheet into the stylesheet field.
	}
	
	public function generateMetadata($externalObject, $shadow = null) {
		$this->_harvester->debug(__CLASS__." is generating metadata.");
		// TODO: Use the $stylesheet to transform the $externalObject into a metadata xml
		// blob that is appended to the $shadow's list of metadata shadows.
	}
	
}