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
	protected $_localStylesheetDirectory;
	
	public function __construct($harvester, $name, $parameters = array()) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
		if(!key_exists('stylesheet', $parameters)) {
			throw new RuntimeException('An XSLTMetadataProcessor has to have a "stylesheet" parameter.');
		}
		$stylesheet = $parameters['stylesheet'];
		if(strstr($stylesheet, 'http://') !== 0 && strstr($stylesheet, 'https://') !== 0) {
			$stylesheet = $this->_harvester->resolvePath($stylesheet);
			if($stylesheet == null) {
				throw new RuntimeException('The "stylesheet" parameter ('.$parameters['stylesheet'].') of a XSLTMetadataProcessor is not readable.');
			} else {
				$this->_localStylesheetDirectory = realpath($stylesheet);
				$this->_localStylesheetDirectory = pathinfo($this->_localStylesheetDirectory);
				$this->_localStylesheetDirectory = $this->_localStylesheetDirectory['dirname'];
				//chdir('cvs');
			}
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
	
	public function generateMetadata($externalObject, &$shadow = null) {
		$this->_harvester->debug(__CLASS__." is generating metadata.");
		
		if($externalObject instanceof DOMDocument) {
			$dom = $externalObject;
		} elseif($externalObject instanceof SimpleXMLElement) {
			$dom = dom_import_simplexml($externalObject)->ownerDocument;
		} else {
			throw new RuntimeException("Cannot generate XMSL metadata from an external object of type ".get_class($externalObject));
		}
		
		// This makes PHP functions reachable from the XSLT stylesheet.
		$this->_processor->registerPHPFunctions();
		
		if(is_array($shadow->extras)) {
			foreach($shadow->extras as $key => $value) {
				if(is_string($value) || is_numeric($value)) {
					$success = $this->_processor->setParameter('', $key, $value);
				}
			}
		}
		
		// We use transformToXml here.
		libxml_clear_errors();
		$result = $this->_processor->transformToXml($dom);
		if($result == null) {
			$errors = libxml_get_errors();
			if($errors) {
				throw new RuntimeException("Failed to transform the external object using the XSLT processor: ". implode(" ", $errors));
			} else {
				throw new RuntimeException("Failed to transform the external object using the XSLT processor.");
			}
		}
		return simplexml_load_string($result);
	}

	public static function xslt_split($pieces, $glue = ',') {
		$d = new DomDocument('1.0');
		$pieces = explode($glue, $pieces);
		foreach($pieces as $p){
			$p = trim($p);
			if($p === '') continue;
			$n = $d->createElement('element', $p);
			$d->appendChild($n);
		}
		return $d;
	}
	
	/**
	 * Creates an element from a document.
	 * @param \DOMDocument $d
	 * @param string $tagName
	 * @param string $value
	 * @param string $ns
	 * @return \DOMElement
	 */
	protected static function createElement($d, $tagName, $value = null, $ns = null) {
		if($ns != null && strlen($ns) > 0) {
			return $d->createElementNS($ns, $tagName, $value);
		} else {
			return $d->createElement($tagName, $value);
		}
	}
	
		
	function preg_explode_to_xml($s, $pattern, $rootTagName = "root", $matchTagName = "match", $ns = "", $valuesAsAttributes = false, $singleGroupAsNodeValue = false) {
		$d = new \DOMDocument('1.0');
		$root = self::createElement($d, $rootTagName, null, $ns);
		
		$matches = preg_match_all($pattern, $s, $result);
		$groupNames = array();
		
		foreach(array_keys($result) as $groupName) {
			if(is_string($groupName)) {
				$groupNames[] = $groupName;
			}
		}
		
		for($m = 0; $m < $matches; $m++) {
			$match = self::createElement($d, $matchTagName, null, $ns);
			if($singleGroupAsNodeValue) {
				$value = $result[1][$m];
				$match->nodeValue = $value;
			} else {
				foreach(array_keys($result) as $groupName) {
					if(is_string($groupName)) {
						$value = $result[$groupName][$m];
						if($valuesAsAttributes == true) {
							// If the group is value, use it as the nodes value.
							if(strtolower($groupName) == 'nodeValue') {
								$match->nodeValue = $value;
							} else {
								$match->setAttribute($groupName, $value);
							}
						} else {
							$group = self::createElement($d, $groupName, $value, $ns);
							$match->appendChild($group);
						}
					}
				}
			}
			$root->appendChild($match);
		}
		
		$d->appendChild($root);
		$d->formatOutput = true;
		return $d;
	}
	
}