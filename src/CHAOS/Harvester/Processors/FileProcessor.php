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
	 * @var string[][]
	 */
	protected $_destinations;
	
	/**
	 * Set the destinations to use when creating files.
	 * @param unknown_type $destinationId
	 */
	public function setDestinations($destinations) {
		$this->_destinations = $destinations;
	}
	
	public function createFileShadowFromURL($url) {
		$url = strval($url);
		// Check if a destination exists that can be used for this file.
		foreach($this->_destinations as $destination) {
			$baseURL = $destination['baseURL'];
			if(preg_match("#$baseURL(.*)#", $url, $matches)) {
				// We found a matching destination
				if(!$this->shouldCheckFileExistance() || $this->externalFileExists($url)) {
					$pathinfo = $this->extractURLPathinfo($matches[1]);
					return $this->createFileShadow($destination['id'], $pathinfo['dirname'], $pathinfo['basename']);
				}
			}
		}
		return null;
	}
	
	/**
	 * Create a FileShadow representing the parameters given as arguments.
	 * @param string $folderPath The folder path of the file.
	 * @param string $filename The filename of the file.
	 * @param string|null $originalFilename The original filename of the file, before it was possibly altered. If null: The $filename is used.
	 * @param int|null $parentFileID The id of the file from which this is derived. If null: This is the original file.
	 * @return \CHAOS\Harvester\Shadows\FileShadow The FileShadow representing the parameters given as arguments.
	 */
	public function createFileShadow($destinationId, $folderPath, $filename, $originalFilename = null, $parentFileID = null) {
		if($originalFilename == null) {
			$originalFilename = $filename;
		}
		$result = new FileShadow();
		$result->formatID = $this->_formatId;
		$result->destinationID = $destinationId;
		$result->folderPath = $folderPath;
		$result->filename = $filename;
		$result->originalFilename = $originalFilename;
		$result->parentFileID = $parentFileID;
		return $result;
	}
	
	protected $fileExistsCurlHandle = null;
	
	/**
	 * Checks if an external file exists or not.
	 * @param unknown_type $fileURL
	 * @return bool True if the file exists.
	 */
	protected function externalFileExists($fileURL) {
		timed();
		if($this->fileExistsCurlHandle == null) {
			$this->fileExistsCurlHandle = curl_init();
			curl_setopt($this->fileExistsCurlHandle, CURLOPT_HEADER, true);
			curl_setopt($this->fileExistsCurlHandle, CURLOPT_NOBODY, true);
			curl_setopt($this->fileExistsCurlHandle, CURLOPT_RETURNTRANSFER, true);
		}
		curl_setopt($this->fileExistsCurlHandle, CURLOPT_URL, $fileURL);
		$response = curl_exec($this->fileExistsCurlHandle);
		$result = $this->concludeFileExistance($response);
		timed('checking-file-existance');
		return $result;
	}
	
	/**
	 * Given a response string with the header of a cURL call on a particular URL, determine if this file exists or not.
	 * @param string $response The response from a cURL call.
	 * @throws \Exception If this method has not been implemented by the specialization.
	 * @return bool True if the file exists.
	 */
	protected function concludeFileExistance($response) {
		throw new \Exception("concludeFileExistance was not implemented by the specialization.");
	}
	
	/**
	 * If the configuration says that the harvester should check for the existance of files for a particular file processor, this returns true.
	 * @return boolean True if the existance of the file should infact be checked.
	 */
	protected function shouldCheckFileExistance() {
		return key_exists('CheckFileExistance', $this->_parameters) && $this->_parameters['CheckFileExistance'] == 'true';
	}
	
	/**
	 * Extract the pathinfo of a url, overwrite this method to manipulate the pathinfo before its used in chaos.
	 * @param string $url The URL to extract it from.
	 */
	protected function extractURLPathinfo($url) {
		// Default implementation
		return pathinfo($url);
	}
	
}