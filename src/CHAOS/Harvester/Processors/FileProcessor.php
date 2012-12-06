<?php
namespace CHAOS\Harvester\Processors;

use CHAOS\Harvester\Shadows\FileShadow;

use CHAOS\Harvester\Shadows\MetadataShadow;
use CHAOS\Harvester\Shadows\ObjectShadow;
use \RuntimeException;

abstract class FileProcessor extends Processor implements \CHAOS\Harvester\Loadable {
	
	/**
	 * The ID of the format to use when creating files.
	 * @var int
	 */
	protected $_formatId;
	
	/**
	 * Set the format id to use when creating files.
	 * @param unknown_type $formatId
	 */
	public function setFormatId($formatId) {
		$this->_formatId = $formatId;
	}
	
	/**
	 * The ID of the destination to use when creating files.
	 * @var int
	 */
	protected $_destinationId;
	
	/**
	 * Set the destination id to use when creating files.
	 * @param unknown_type $destinationId
	 */
	public function setDestinationId($destinationId) {
		$this->_destinationId = $destinationId;
	}
	
	/**
	 * Create a FileShadow representing the parameters given as arguments.
	 * @param string $folderPath The folder path of the file.
	 * @param string $filename The filename of the file.
	 * @param string|null $originalFilename The original filename of the file, before it was possibly altered. If null: The $filename is used.
	 * @param int|null $parentFileID The id of the file from which this is derived. If null: This is the original file.
	 * @return \CHAOS\Harvester\Shadows\FileShadow The FileShadow representing the parameters given as arguments.
	 */
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