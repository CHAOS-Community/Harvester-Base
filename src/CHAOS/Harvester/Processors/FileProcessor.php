<?php
namespace CHAOS\Harvester\Processors;

use CHAOS\Harvester\Shadows\FileShadow;

use CHAOS\Harvester\Shadows\MetadataShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

abstract class FileProcessor extends Processor implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name, $parameters = null) {
		$this->_harvester = $harvester;
		$this->setParameters($parameters);
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	protected $_formatId;
	
	public function setFormatId($formatId) {
		$this->_formatId = $formatId;
	}
	
	protected $_destinationId;
	
	public function setDestinationId($destinationId) {
		$this->_destinationId = $destinationId;
	}
	
	public function createFileShadow($folderPath, $filename, $originalFilename = null, $parentFileID = null) {
		if($originalFilename == null) {
			$originalFilename = $filename;
		}
		$result = new FileShadow();
		$result->destinationID = $this->_destinationId;
		$result->formatID = $this->_formatId;
		$result->folderPath = $folderPath;
		$result->filename = $filename;
		$result->originalFilename = $originalFilename;
		$result->parentFileID = $parentFileID;
		return $result;
	}
	
}