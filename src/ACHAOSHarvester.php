<?php
/**
 * This abstract harvester copies information into a CHAOS service.
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    $Id:$
 * @link       https://github.com/CHAOS-Community/Harvester-Base
 * @since      File available since Release 0.1
 */

/**
 * An abstract harvester.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    Release: @package_version@
 * @link       https://github.com/CHAOS-Community/Harvester-Base
 * @since      Class available since Release 0.1
 */

// The Timed-PHP lib see: https://github.com/kraenhansen/Timed-PHP
require_once("timed.php");

use CHAOS\Portal\Client\PortalClient;

abstract class ACHAOSHarvester {
	
	const PROCESS_RETRIES = 3;
	
	/**
	 * The CHAOS Portal client to be used for communication with the CHAOS Service.
	 * @var PortalClient
	 */
	public $_chaos;
	
	/**
	 * This is a collection of CHAOSXMLGenerators to use when generating XML for a specific external object.
	 * This should be filled with initialized objects of the CHAOSXMLGenerator class before the parent constructor is called. 
	 * @var CHAOSXMLGenerator[]
	 */
	protected $_metadataGenerators = array();
	
	/**
	 * This is a collection of CHAOSFileExtractors to use when generating files for the specific external object.
	 * This should be filled with initialized objects of the CHAOSFileExtractors class before the parent constructor is called.
	 * @var CHAOSFileExtractor[]
	 */
	protected $_fileExtractors = array();
	
	/**
	 * The generated unique ID of the CHAOS Client.
	 * (can be generated at http://www.guidgenerator.com/)
	 * @var string
	 */
	protected $_CHAOSClientGUID;
	/**
	 * The URL of the CHAOS service.
	 * @var string
	 */
	protected $_CHAOSURL;
	/**
	 * The email to be used to authenticate sessions from the CHAOS service.
	 * @var string
	 */
	protected $_CHAOSEmail;
	/**
	 * The password to be used to authenticate sessions from the CHAOS service.
	 * @var string
	 */
	protected $_CHAOSPassword;
	
	protected $_CHAOSFolderID;
	
	/**
	 * An associative array describing the configuration parameters for the harvester.
	 * This should ideally not be changed.
	 * @var array[string]string
	 */
	protected $_CONFIGURATION_PARAMETERS = array(
			"CHAOS_CLIENT_GUID" => "_CHAOSClientGUID",
			"CHAOS_URL" => "_CHAOSURL",
			"CHAOS_EMAIL" => "_CHAOSEmail",
			"CHAOS_PASSWORD" => "_CHAOSPassword",
			"CHAOS_FOLDER_ID" => "_CHAOSFolderID"
	);
	
	public function __construct() {
		$this->loadConfiguration();
		$this->CHAOS_initialize();
	}
	
	public function __destruct() {
		unset($this->_chaos);
	}
	
	/**
	 * Main method of the harvester, call this once.
	 * @param string[] $args Array of arguments.
	 * @throws InvalidArgumentException
	 */
	function main($args = array()) {
		$harvester_class = get_called_class();
		printf("Harvester (%s) started %s.\n", $harvester_class, date('D, d M Y H:i:s'));
	
		try {
			// Processing runtime options.
				
			$runtimeOptions = self::extractOptionsFromArguments($args);
			
			// TODO: Remember to move the behaviour of these parameters directly into the process methods.
			/*
			$publish = null;
			$publishAccessPointGUID = null;
			$skipProcessing = null;
			if(array_key_exists('publish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['publish'];
				$publish = true;
			}
			if(array_key_exists('just-publish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['just-publish'];
				$skipProcessing = true;
				$publish = true;
			}
			if($publish === true && array_key_exists('unpublish', $runtimeOptions)) {
				throw new InvalidArgumentException("Cannot have both publish or just-publish and unpublish options sat.");
			} elseif(array_key_exists('unpublish', $runtimeOptions)) {
				$publishAccessPointGUID = $runtimeOptions['unpublish'];
				$publish = false;
			}
			*/
	
			// Starting on the real job at hand
			$starttime = time();
			$h = new $harvester_class();
			
			if(array_key_exists('range', $runtimeOptions)) {
				$rangeParams = explode('-', $runtimeOptions['range']);
				if(count($rangeParams) == 2) {
					$start = intval($rangeParams[0]);
					$end = intval($rangeParams[1]);
					if($end < $start) {
						throw new InvalidArgumentException("Given a range parameter which has end < start.");
					} else {
						$count = $end-$start+1;
						printf("Harvesting a range of external objects (%u items starting from %u).\n", $count, $start);
						$h->harvestRange($start, $count);
						printf("Done harvesting a range of external objects.\n");
					}
				} else {
					throw new InvalidArgumentException("Given a range parameter was malformed.");
				}
			} elseif(array_key_exists('single-id', $runtimeOptions)) {
				$externalID = intval($runtimeOptions['single-id']);
				printf("Harvesting a single external object (id = %u).\n", $externalID);
				$h->harvestSingle($externalID);
				printf("Done harvesting a signle object.\n");
			} elseif(array_key_exists('all', $runtimeOptions) && $runtimeOptions['all'] == true) {
				$h->harvestAll();
			} else {
				throw new InvalidArgumentException("None of --all, --single or --range was sat.");
			}
		} catch(InvalidArgumentException $e) {
			echo "\n";
			printf("Invalid arguments given: %s\n", $e->getMessage());
			self::printUsage($args);
			exit;
		} catch (RuntimeException $e) {
			echo "\n";
			printf("An unexpected runtime error occured: %s\n", $e->getMessage());
			exit;
		} catch (Exception $e) {
			echo "\n";
			printf("Error occured in the harvester implementation: %s\n", $e);
			exit;
		}
	
		// If the handle to the harvester has not been deallocated already.
		if($h !== null) {
			unset($h);
		}
	
		$elapsed = time() - $starttime;
		timed_print();
	}
	
	protected static function printUsage($args) {
		printf("Usage:\n\t%s [--all|--single-id={dfi-id}|--range={start-row}-{end-row}] [--publish={access-point-guid}|--unpublish={access-point-guid}] --skip-processing\n", $args[0]);
	}
	
	protected function harvestAll() {
		$this->harvestRange();
	}
	
	protected function harvestRange($start = 0, $count = null) {
		$externals = $this->fetchRange($start, $count);

		$failures = array();
		$n = 1;
		if($externals !== null && count($externals) > 0) {
			foreach($externals as $e) {
				$externalObject = null;
				// Determine if the returned is a collection of objects or a collection of references (strings).
				if(is_string($e)) {
					// We got a reference, we need to fetch the object.
					$externalObject = $this->fetchSingle($e);
				} else {
					$externalObject = $e;
				}
				printf("[%u/%u]\n", $n, count($externals));
				
				for($attempt = 1; $attempt <= 3; $attempt++) {
					try {
						$this->processSingle($externalObject);
						
						break; // Break the retry loop.
					} catch(Exception $e) {
						if(strstr($e->getMessage(), 'Session has expired') !== false) {
							printf("[!] Session expired: Creating a new session and trying again.\n");
							// Reauthenticate!
							$this->CHAOS_initialize();
						}
						
						// An error occured.
						if($attempt === self::PROCESS_RETRIES) {
							printf("[!] Exception thrown after ".self::PROCESS_RETRIES." retries: ".$e->getMessage()."\n");
							// For the third time.
							$failures[] = array("externalObject" => $externalObject, "exception" => $e);
						}
					}
				}
				$n++;
			}
		}
		
		if(empty($failures)) {
			printf("Done .. no failures occurred.\n");
		} else {
			printf("Done .. %u failures occurred:\n", count($failures));
			foreach ($failures as $failure) {
				printf("\t%s: %s\n", $this->externalObjectToString($failure["externalObject"]), $failure["exception"]->getMessage());
			}
		}
		//$this->processMovies($start, $count);
	}
	
	protected function harvestSingle($externalId) {
		$externalObject = $this->fetchSingle('http://nationalfilmografien.service.dfi.dk/movie.svc/'.$externalId);
		$this->processSingle($externalObject);
	}
	
	protected abstract function fetchRange($start, $count);
	
	protected abstract function fetchSingle($reference);
	
	protected abstract function externalObjectToString($externalObject);
	
	protected abstract function getOrCreateObject($externalObject);
	
	protected abstract function initializeExtras(&$extras);
	
	public abstract function getExternalClient();
	
	public function getCHAOSClient() {
		return $this->_chaos;
	}
	
	protected function processSingle($externalObject) {
		printf("Processing '%s'\n", $this->externalObjectToString($externalObject));
		
		$object = $this->getOrCreateObject($externalObject);

		// For data generated while processing.
		$extras = array();
		$this->initializeExtras($extras);
		
		print("\tExtracting files:\n");
		
		$xml = $this->extractFiles($object, $externalObject, $extras);
		
		// TODO: Use the $externalObject to look up (or create) the internal CHAOS object to use.
		// TODO: Run through all registrated $this->_xmlGenerators and $this->_fileExtractors
		
		$xml = $this->generateMetadata($externalObject, $extras);
		
		$revisions = self::extractMetadataRevisions($object);
		
		foreach($xml as $schemaGUID => $metadata) {
			// This is not implemented.
			// $currentMetadata = $this->_chaos->Metadata()->Get($object->GUID, $schema->GUID, 'da');
			//var_dump($currentMetadata);
			$revision = array_key_exists($schemaGUID, $revisions) ? $revisions[$schemaGUID] : null;
			printf("\tSetting '%s' metadata on the CHAOS object (overwriting revision %u): ", $schemaGUID, $revision);
			timed();
			$response = $this->_chaos->Metadata()->Set($object->GUID, $schemaGUID, 'da', $revision, $xml[$schemaGUID]->saveXML());
			timed('chaos');
			if(!$response->WasSuccess()) {
				printf("Failed.\n");
				throw new RuntimeException("Couldn't set the metadata on the CHAOS object.");
			} else {
				printf("Succeeded.\n");
			}
		}
		
		printf("\tDone processing a single external object.\n");
	}
	
	/**
	 * This is the "important" method which generates the metadata XML documents from a MovieItem from the DFI service.
	 * @param \dfi\model\MovieItem $movieItem A particular MovieItem from the DFI service, representing a particular movie.
	 * @param bool $validateSchema Should the document be validated against the XML schema?
	 * @throws Exception if $validateSchema is true and the validation fails.
	 * @return DOMDocument Representing the DFI movie in the DKA Program specific schema.
	 */
	protected function extractFiles($object, $externalObject, &$extras) {
		$result = array();
		foreach($this->_fileExtractors as $extractor) {
			$result[get_class($extractor)] = $extractor->process($this, $object, $externalObject, $extras);
		}
		return $result;
	}
	
	/**
	 * This is the "important" method which generates the metadata XML documents from a MovieItem from the DFI service.
	 * @param \dfi\model\MovieItem $movieItem A particular MovieItem from the DFI service, representing a particular movie.
	 * @param bool $validateSchema Should the document be validated against the XML schema?
	 * @throws Exception if $validateSchema is true and the validation fails.
	 * @return DOMDocument Representing the DFI movie in the DKA Program specific schema.
	 */
	/** @deprecated Use a method on the abstract ACHAOSHarvester instead. */
	protected function generateMetadata($externalObject, &$extras) {
		$result = array();
		foreach($this->_metadataGenerators as $generator) {
			$result[$generator::SCHEMA_GUID] = $generator->generateXML($externalObject, $extras);
		}
		return $result;
	}
	
	/**
	 * Initialize the CHAOS part of the harvester.
	 * This involves fetching a session from the service,
	 * authenticating it,
	 * fetching the metadata schema for the DKA Program content,
	 * fetching the object type (DKA Program) to identify its id on the CHAOS service,
	 * @throws \RuntimeException If any service call fails. This might be due to an unadvailable service,
	 * or an unenticipated change in the protocol.
	 */
	protected function CHAOS_initialize() {
		printf("Creating a session for the CHAOS service on %s using clientGUID %s: ", $this->_CHAOSURL, $this->_CHAOSClientGUID);
		// Create a new client, a session is automaticly created.
		timed();
		$this->_chaos = new PortalClient($this->_CHAOSURL, $this->_CHAOSClientGUID);
		timed('chaos');
		if(!$this->_chaos->HasSession()) {
			printf("Failed.\n");
			throw new \RuntimeException("Couldn't establish a session with the CHAOS service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Succeeded: SessionGUID is %s\n", $this->_chaos->SessionGUID());
		}
		timed();
		$this->CHAOS_authenticateSession();
		$this->CHAOS_fetchMetadataSchemas();
		$this->CHAOS_fetchObjectType();
		timed('chaos');
		//$this->CHAOS_fetchDFIFolder();
	}
	
	/**
	 * Authenticate the CHAOS session using the environment variables for email and password.
	 * @throws \RuntimeException If the authentication fails.
	 */
	protected function CHAOS_authenticateSession() {
		printf("Authenticating the session using email %s: ", $this->_CHAOSEmail);
		$result = $this->_chaos->EmailPassword()->Login($this->_CHAOSEmail, $this->_CHAOSPassword);
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new \RuntimeException("Couldn't authenticate the session, please check the CHAOS_EMAIL and CHAOS_PASSWORD parameters.");
		} else {
			printf("Succeeded.\n");
		}
	}
	
	/**
	 * Fetches the DKA Program metadata schema and stores it in the _DKAMetadataSchema field.
	 * @throws \RuntimeException If it fails.
	 */
	protected function CHAOS_fetchMetadataSchemas() {
		printf("Fetching metadata %u schemas: ", count($this->_metadataGenerators));
	
		// Looping throug every XML generator.
		foreach($this->_metadataGenerators as $generator) {
			if(is_subclass_of($generator, 'ACHAOSMetadataGenerator')) {
				 $generator->fetchSchema($this->_chaos);
			}
		}
	
		printf("Succeeded.\n");
	}
	
	protected abstract function CHAOS_fetchObjectType();
	
	protected function CHAOS_fetchObjectTypeFromName($name) {
		if(empty($name)) {
			throw new InvalidArgumentException("The name of the object type has to be sat.");
		}
		$response = $this->_chaos->ObjectType()->Get();
		if(!$response->WasSuccess()) {
			throw new RuntimeException("Couldn't lookup the '$name' object type.");
		}
		
		$result = null;
		foreach($response->MCM()->Results() as $objectType) {
			if($objectType->Name == $name) {
				$result = $objectType;
				break;
			}
		}
		
		if($result == null) {
			throw new RuntimeException("Couldn't find the '$name' object type.");
		} else {
			return $result;
		}
	}
	
	/**
	 * Extract the revisions for the metadata currently associated with the object.
	 */
	public static function extractMetadataRevisions($object) {
		$result = array();
		foreach($object->Metadatas as $metadata) {
			// The schema matches the metadata.
			$result[strtolower($metadata->MetadataSchemaGUID)] = $metadata->RevisionID;
		}
		return $result;
	}
	
	protected static function extractOptionsFromArguments($args) {
		$result = array();
		for($i = 0; $i < count($args); $i++) {
			if(strpos($args[$i], '--') === 0) {
				$equalsIndex = strpos($args[$i], '=');
				if($equalsIndex === false) {
					$name = substr($args[$i], 2);
					$result[$name] = true;
				} else {
					$name = substr($args[$i], 2, $equalsIndex-2);
					$value = substr($args[$i], $equalsIndex+1);
					if($value == 'true') {
						$result[$name] = true;
					} elseif($value == 'false') {
						$result[$name] = false;
					} else {
						$result[$name] = $value;
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * Load the configuration parameters from the string[] argument provided.
	 * @param array[string]string $config An (optional) associative array holding the array
	 * of configuration parameters, defaults to the $_SERVER array.
	 * @throws \RuntimeException if an expected environment variable is not sat.
	 * @throws \Exception if the CONFIGURATION_PARAMETERS holds a value which is
	 * not a member of the class. This should not be possible.
	 */
	public function loadConfiguration($config = null) {
		if($config == null) {
			$config = $_SERVER; // Default to the server array.
		}
		$this_class = get_class($this);
		foreach($this->_CONFIGURATION_PARAMETERS as $param => $fieldName) {
			if(!key_exists($param, $config)) {
				throw new \RuntimeException("The environment variable $param is not sat.");
			} elseif (!property_exists($this_class, $fieldName)) {
				throw new \Exception("CONFIGURATION_PARAMETERS contains a value ($fieldName) for a param ($param) which is not a property for the class ($this_class).");
			} else {
				$this->$fieldName = $config[$param];
			}
		}
	}
	
	protected $progressTotal;
	protected $progressWidth;
	protected $progressDotsPrinted;
	const PROGRESS_DOT_CHAR = '-';
	const PROGRESS_END_CHAR = '|';
	
	public function resetProgress($total, $width = 30) {
		if($total > 0) {
			$this->progressTotal = $total;
			$this->progressWidth = $width;
			$this->progressDotsPrinted = 0;
			echo self::PROGRESS_END_CHAR;
		} else {
			// Reset ...
			$this->progressTotal = 0;
			updateProgress(0);
		}
	}
	
	public function updateProgress($value) {
		if($this->progressTotal <= 1 && $value == 0) {
			$ratioDone = 1;
		} else {
			$ratioDone = $value / ($this->progressTotal - 1);
		}
		$dots = (int) round( $ratioDone * $this->progressWidth);
		//printf("updateProgress(\$value = %s) ~ \$dots = %u\n", $value, $dots);
		while($this->progressDotsPrinted < $dots) {
			echo self::PROGRESS_DOT_CHAR;
			$this->progressDotsPrinted++;
		}
		if($dots >= $this->progressWidth) {
			echo self::PROGRESS_END_CHAR;
		}
	}
}