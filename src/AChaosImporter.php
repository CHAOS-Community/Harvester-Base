<?php
/**
 * This abstract harvester copies information into a Chaos service.
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

abstract class AChaosImporter {
	
	const PROCESS_RETRIES = 3;
	
	/**
	 * The Chaos Portal client to be used for communication with the Chaos Service.
	 * @var PortalClient
	 */
	public $_chaos;
	
	protected $runtimeOptions;
	
	/**
	 * This is a collection of ChaosXMLGenerators to use when generating XML for a specific external object.
	 * This should be filled with initialized objects of the ChaosXMLGenerator class before the parent constructor is called. 
	 * @var ChaosXMLGenerator[]
	 */
	protected $_metadataGenerators = array();
	
	/**
	 * This is a collection of ChaosFileExtractors to use when generating files for the specific external object.
	 * This should be filled with initialized objects of the ChaosFileExtractors class before the parent constructor is called.
	 * @var ChaosFileExtractor[]
	 */
	protected $_fileExtractors = array();
	
	/**
	 * The generated unique ID of the Chaos Client.
	 * (can be generated at http://www.guidgenerator.com/)
	 * @var string
	 */
	protected $_ChaosClientGUID;
	/**
	 * The URL of the Chaos service.
	 * @var string
	 */
	protected $_ChaosURL;
	/**
	 * The email to be used to authenticate sessions from the Chaos service.
	 * @var string
	 */
	protected $_ChaosEmail;
	/**
	 * The password to be used to authenticate sessions from the Chaos service.
	 * @var string
	 */
	protected $_ChaosPassword;
	
	protected $_ChaosFolderID;
	
	/**
	 * An associative array describing the configuration parameters for the harvester.
	 * This should ideally not be changed.
	 * @var array[string]string
	 */
	protected $_CONFIGURATION_PARAMETERS = array(
			"CHAOS_CLIENT_GUID" => "_ChaosClientGUID",
			"CHAOS_URL" => "_ChaosURL",
			"CHAOS_EMAIL" => "_ChaosEmail",
			"CHAOS_PASSWORD" => "_ChaosPassword",
			"CHAOS_FOLDER_ID" => "_ChaosFolderID"
	);
	
	public function __construct($args) {
		$this->loadConfiguration($args);
		$this->ChaosInitialize();
	}
	
	/**
	 * This destructs the harvester, this also unsets/destroys the chaos client.
	 */
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
	
			// Starting on the real job at hand
			$starttime = time();
			$h = new $harvester_class($args);
			
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
						//$ObjectGUIDs = $h->harvestRange($start, $count);
						//$h->syncronize($ObjectGUIDs);
						printf("Done harvesting a range of external objects.\n");
					}
				} else {
					throw new InvalidArgumentException("Given a range parameter was malformed.");
				}
			} elseif(array_key_exists('single', $runtimeOptions)) {
				$externalID = intval($runtimeOptions['single']);
				printf("Harvesting a single external object (id = %u).\n", $externalID);
				$h->harvestSingle($externalID);
				printf("Done harvesting a signle object.\n");
			} elseif(array_key_exists('all', $runtimeOptions) && $runtimeOptions['all'] == true) {
				$ObjectGUIDs = $h->harvestAll();
				$h->syncronize($ObjectGUIDs);
			} else {
				throw new InvalidArgumentException("None of --all, --single or --range was sat.");
			}
		} catch(InvalidArgumentException $e) {
			echo "\n";
			self::error_log(sprintf("[!] Invalid arguments given: %s\n", $e->getMessage()));
			self::printUsage($args);
			exit(1);
		} catch (RuntimeException $e) {
			echo "\n";
			self::error_log(sprintf("[!] Something went wrong: %s\n", $e->getMessage()));
			exit(2);
		} catch (Exception $e) {
			echo "\n";
			self::error_log(sprintf("[!] Error occured in the harvester implementation: %s\n", $e));
			exit(3);
		}
	
		// If the handle to the harvester has not been deallocated already.
		if($h !== null) {
			unset($h);
		}
	
		$elapsed = time() - $starttime;
		timed_print();
	}
	
	protected static function error_log($message) {
		//error_log($message);
		echo $message;
	}
	
	protected static function printUsage($args) {
		printf("Usage:\n\t%s [--all|--single={external-id}|--range={start-row}-{end-row}] [--publish={access-point-guid}|--unpublish={access-point-guid}] [--skip-processing] [--sync=dump={filename}]\n", $args[0]);
	}
	
	protected abstract function fetchRange($start, $count);
	
	protected abstract function fetchSingle($reference);
	
	protected abstract function externalObjectToString($externalObject);
	
	protected abstract function initializeExtras($externalObject, &$extras);
	
	protected abstract function shouldBeSkipped($externalObject);
	
	protected abstract function generateChaosQuery($externalObject);
	
	protected abstract function getChaosObjectTypeID();
	
	public abstract function getExternalClient();
	
	protected function harvestAll() {
		return $this->harvestRange();
	}
	
	protected function harvestRange($start = 0, $count = null) {
		$objectGUIDs = array();
		$externals = $this->fetchRange($start, $count);

		$failures = array();
		$n = 0;
		if($externals !== null && count($externals) > 0) {
			foreach($externals as $external) {
				$n++;
				$externalObject = null;
				printf("[%u/%u]\n", $n, count($externals));
				// Determine if the returned is a collection of objects or a collection of references (strings).
				
				for($attempt = 1; $attempt <= 3; $attempt++) {
					try {
						// TODO: Check that this actually works.
						if(is_string($external)) {
							// We got a reference, we need to fetch the object.
							$externalObject = $this->fetchSingle($external);
						} else {
							$externalObject = $external;
						}
						$objectGUID = $this->processSingle($externalObject);
						if($objectGUID != null) {
							$objectGUIDs[] = $objectGUID;
						}
						
						break; // Break the retry loop.
					} catch(Exception $e) {
						if(strstr($e->getMessage(), 'Session has expired') !== false) {
							self::error_log(sprintf("[!] Session expired: Creating a new session and trying again.\n"));
							// Reauthenticate!
							$this->ChaosInitialize();
						} else {
							self::error_log(sprintf("\t[!] An error occured: \"%s\"\n", $e->getMessage()));
						}
						
						// An error occured.
						if($attempt === self::PROCESS_RETRIES) {
							self::error_log(sprintf("\t[!] Exception thrown after %u retries: \"%s\" in %s:%u\n", self::PROCESS_RETRIES, $e->getMessage(), $e->getFile(), $e->getLine()));
							// For the third time.
							$failures[] = array("externalObject" => $externalObject, "exception" => $e);
						}
					}
				}
				$this->ChaosKeepSessionAlive();
			}
		}
		
		if(empty($failures)) {
			printf("Done .. no failures occurred.\n");
		} else {
			printf("Done .. %u failures occurred:\n", count($failures));
			foreach ($failures as $failure) {
				if(array_key_exists('externalObject', $failure)) {
					$external = $this->externalObjectToString($failure["externalObject"]);
				} elseif (array_key_exists('externalReference', $failure)) {
					$external = $failure["externalReference"];
				} else {
					$external = '?';
				}
				printf("\t%s: %s\n", $external, $failure["exception"]->getMessage());
			}
		}
		
		return $objectGUIDs;
	}
	
	protected function harvestSingle($externalId) {
		$externalObject = $this->fetchSingle($externalId);
		$this->processSingle($externalObject);
	}
	
	public function getChaosClient() {
		return $this->_chaos;
	}
	
	protected function processSingle($externalObject) {
		printf("Processing '%s'\n", $this->externalObjectToString($externalObject));
		
		$shouldBeSkipped = $this->shouldBeSkipped($externalObject);
		if($shouldBeSkipped !== false) {
			printf("\tSkipping this because '%s'\n", $shouldBeSkipped);
			return;
		}
		
		$object = $this->getOrCreateObject($externalObject);
		
		if(array_key_exists('skip-processing', $this->runtimeOptions)) {
			printf("\tSkipping ...\n");
		} else {
			// For data generated while processing.
			$extras = array();
			$this->initializeExtras($externalObject, $extras);
			
			print("\tExtracting files:\n");
			
			$files = $this->extractFiles($object, $externalObject, $extras);
			$extras['extractedFiles'] = $files;
			
			// TODO: Use the $externalObject to look up (or create) the internal Chaos object to use.
			// TODO: Run through all registrated $this->_xmlGenerators and $this->_fileExtractors
			
			$xml = $this->generateMetadata($externalObject, $extras);
			
			$revisions = self::extractMetadataRevisions($object);
			
			foreach($xml as $schemaGUID => $metadata) {
				// This is not implemented.
				// $currentMetadata = $this->_chaos->Metadata()->Get($object->GUID, $schema->GUID, 'da');
				//var_dump($currentMetadata);
				$revision = array_key_exists($schemaGUID, $revisions) ? $revisions[$schemaGUID] : null;
				printf("\tSetting '%s' metadata on the Chaos object (overwriting revision %u): ", $schemaGUID, $revision);
				$rawXML = $xml[$schemaGUID]->saveXML();
				//echo $rawXML;
				timed();
				$response = $this->_chaos->Metadata()->Set($object->GUID, $schemaGUID, 'da', $revision, $rawXML);
				timed('chaos');
				if(!$response->WasSuccess()) {
					printf("Failed.\n");
					throw new RuntimeException("Couldn't set the metadata on the Chaos object.");
				} else {
					printf("Succeeded.\n");
				}
			}
		}
		
		if(array_key_exists('publish', $this->runtimeOptions) || array_key_exists('unpublish', $this->runtimeOptions)) {
			$publish = array_key_exists('publish', $this->runtimeOptions);
			$accessPointGUID = $publish ? $this->runtimeOptions['publish'] : $this->runtimeOptions['unpublish'];
			
			$start = null;
			if($publish === true) {
				$start = new DateTime();
				printf("\tChanging the publish settings for %s to startDate = %s: ", $accessPointGUID, $start->format("Y-m-d H:i:s"));
			} elseif($publish === false) {
				printf("\tChanging the publish settings for %s to unpublished: ", $accessPointGUID);
			}
			timed();
			$response = $this->_chaos->Object()->SetPublishSettings($object->GUID, $accessPointGUID, $start);
			timed('chaos');
			if(!$response->WasSuccess() || !$response->MCM()->WasSuccess()) {
				printf("Failed.\n");
				throw new RuntimeException("Couldn't set the publish settings on the Chaos object.");
			} else {
				printf("Succeeded.\n");
			}
		}
		
		printf("\tDone processing a single external object.\n");
		return $object->GUID;
	}
	
	protected function getOrCreateObject($externalObject) {
		$query = $this->generateChaosQuery($externalObject);
		$objectTypeID = $this->getChaosObjectTypeID();
		
		if(empty($query) || !is_string($query)) {
			throw new Exception("The implemented class returned an empty query");
		}
		
		timed();
		$response = $this->_chaos->Object()->Get($query, "DateCreated+desc", null, 0, 100, true, true);
		timed('chaos');
		
		if(!$response->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: (Request error) ". $response->Error()->Message());
		} else if(!$response->MCM()->WasSuccess()) {
			throw new RuntimeException("Couldn't complete the request for a movie: (MCM error) ". $response->MCM()->Error()->Message());
		}
		
		$results = $response->MCM()->Results();
		
		// If it's not there, create it.
		if($response->MCM()->TotalCount() == 0) {
			printf("\tFound a film in the DFI service which is not already represented by a Chaos object.\n");
			timed();
			$response = $this->_chaos->Object()->Create($objectTypeID, $this->_ChaosFolderID);
			timed('chaos');
			if($response == null) {
				throw new RuntimeException("Couldn't create a DKA Object: response object was null.");
			} else if(!$response->WasSuccess()) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->Error()->Message());
			} else if(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException("Couldn't create a DKA Object: ". $response->MCM()->Error()->Message());
			} else if ($response->MCM()->TotalCount() != 1) {
				throw new RuntimeException("Couldn't create a DKA Object .. No errors but no object created.");
			}
			$results = $response->MCM()->Results();
		} else {
			printf("\tReusing Chaos object with GUID = %s.\n", $results[0]->GUID);
		}
		return $results[0];
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
			$files = $extractor->process($this, $object, $externalObject, $extras);
			$result[get_class($extractor)] = $files;
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
	/** @deprecated Use a method on the abstract AChaosImporter instead. */
	protected function generateMetadata($externalObject, &$extras) {
		$result = array();
		foreach($this->_metadataGenerators as $generator) {
			$result[$generator::SCHEMA_GUID] = $generator->generateXML($externalObject, $extras);
		}
		return $result;
	}
	
	/**
	 * Initialize the Chaos part of the harvester.
	 * This involves fetching a session from the service,
	 * authenticating it,
	 * fetching the metadata schema for the DKA Program content,
	 * fetching the object type (DKA Program) to identify its id on the Chaos service,
	 * @throws \RuntimeException If any service call fails. This might be due to an unadvailable service,
	 * or an unenticipated change in the protocol.
	 */
	protected function ChaosInitialize() {
		printf("Creating a session for the Chaos service on %s using clientGUID %s: ", $this->_ChaosURL, $this->_ChaosClientGUID);
		// Create a new client, a session is automaticly created.
		timed();
		$this->_chaos = new PortalClient($this->_ChaosURL, $this->_ChaosClientGUID);
		timed('chaos');
		if(!$this->_chaos->HasSession()) {
			printf("Failed.\n");
			throw new \RuntimeException("Couldn't establish a session with the Chaos service, please check the CHAOS_URL configuration parameter.");
		} else {
			printf("Succeeded: SessionGUID is %s\n", $this->_chaos->SessionGUID());
		}
		timed();
		$this->ChaosAuthenticateSession();
		$this->ChaosFetchMetadataSchemas();
		//$this->CHAOS_fetchObjectType();
		timed('chaos');
		//$this->CHAOS_fetchDFIFolder();
		$_lastSessionUpdate = time();
	}
	
	/**
	 * Authenticate the Chaos session using the environment variables for email and password.
	 * @throws \RuntimeException If the authentication fails.
	 */
	protected function ChaosAuthenticateSession() {
		printf("Authenticating the session using email %s: ", $this->_ChaosEmail);
		$result = $this->_chaos->EmailPassword()->Login($this->_ChaosEmail, $this->_ChaosPassword);
		if(!$result->WasSuccess()) {
			printf("Failed.\n");
			throw new \Exception("Couldn't authenticate the session, error in request.");
		} elseif(!$result->EmailPassword()->WasSuccess()) {
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
	protected function ChaosFetchMetadataSchemas() {
		printf("Fetching metadata %u schemas: ", count($this->_metadataGenerators));
	
		// Looping throug every XML generator.
		foreach($this->_metadataGenerators as $generator) {
			if(is_subclass_of($generator, 'AChaosMetadataGenerator')) {
				 $generator->fetchSchema($this->_chaos);
			}
		}
	
		printf("Succeeded.\n");
	}
	
	// protected abstract function CHAOS_fetchObjectType();
	
	protected function ChaosFetchObjectTypeFromName($name) {
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
	
	const SESSION_UPDATE_INTERVAL = 900; // Every 15 minutes.
	protected $_lastSessionUpdate;
	protected function ChaosKeepSessionAlive() {
		$now = time();
		if($this->_lastSessionUpdate == null || $this->_lastSessionUpdate + self::SESSION_UPDATE_INTERVAL < $now) {
			printf("Updating chaos session: ");
			$response = $this->_chaos->Session()->Update();
			if($response->WasSuccess() && $response->Portal()->WasSuccess()) {
				printf("Success.\n");
				$this->_lastSessionUpdate = $now;
			} else {
				printf("Failed.\n");
			}
		}
	}
	
	/**
	 * Extract the revisions for the metadata currently associated with the object.
	 */
	public static function extractMetadataRevisions($object) {
		if($object === null) {
			throw new Exception('Cannot extract metadata revisions from a null object, is the getOrCreateObject method implemented correctly?');
		}
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
	public function loadConfiguration($args, $config = null) {
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
		$this->runtimeOptions = self::extractOptionsFromArguments($args);
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
	
	public function getAllIdentifiers($query) {
		$result = array();
		$pageIndex = 0;
		$pageSize = 500;
		$total = 0;
		do {
			timed();
			$response = $this->_chaos->Object()->Get($query, null, null, $pageIndex, $pageSize);
			timed('chaos');
			$totalCount = $response->MCM()->totalCount();
			foreach($response->MCM()->Results() as $object) {
				$result[] = $object->GUID;
			}
			printf("Fetching GUIDs of existing objects (%u/%u).\n", $pageIndex+1, ceil($totalCount/$pageSize));
			
			$pageIndex++;
		} while (count($result) < $totalCount);
		return $result;
	}
	
	public function syncronize($harvestedGUIDs) {
		if(array_key_exists('sync', $this->runtimeOptions)) {
			printf("Syncronizing objects (dealing with objects which have been removed from the external service).\n");
			$folderID = $this->_ChaosFolderID;
			$objectTypeID = $this->getChaosObjectTypeID();
			// Query for a Chaos Object that represents the DFI movie.
			// TODO: Consider putting this in the query: (PubStart:[*+TO+NOW]+AND+PubEnd:[NOW+TO+*])
			$totalObjectsQuery = "(FolderTree:$folderID AND ObjectTypeID:$objectTypeID AND PubStart:[* TO NOW] AND PubEnd:[NOW TO *])";
			//$totalObjectsQuery = "(FolderTree:$folderID AND ObjectTypeID:$objectTypeID)";
			
			// public function Get($query, $sort, $accessPointGUID, $pageIndex, $pageSize, $includeMetadata = false, $includeFiles = false, $includeObjectRelations = false, $includeAccessPoints = false)
			$allGUIDs = $this->getAllIdentifiers($totalObjectsQuery);
			
			printf("The harvester touched %u objects, the chaos service has %u in the folder.\n", count($harvestedGUIDs), count($allGUIDs));
			printf("Generating removed GUIDs: ");
			$removedGUIDs = array_diff($allGUIDs, $harvestedGUIDs);
			printf("Found %u.\n", count($removedGUIDs));
			
			if($this->runtimeOptions['sync'] == 'delete') {
				// TODO: Implement this.
				throw new RuntimeException("Not implemented ...");
			} elseif(strpos($this->runtimeOptions['sync'], 'unpublish') === 0) {
				$args = explode('=', $this->runtimeOptions['sync']);
				if(count($args) == 2) {
					$accessPointGUID = $args[1];
					$index = 1;
					foreach($removedGUIDs as $GUID) {
						$index++;
						printf("\t[%u/%u] Changing the publish settings for %s on %s to unpublished: ", $index, count($removedGUIDs), $GUID, $accessPointGUID);
						
						timed();
						$response = $this->_chaos->Object()->SetPublishSettings($GUID, $accessPointGUID, null);
						$this->ChaosKeepSessionAlive();
						timed('chaos');
						
						if(!$response->WasSuccess() || !$response->MCM()->WasSuccess()) {
							printf("Failed.\n");
							//throw new RuntimeException("Couldn't set the publish settings on the Chaos object.");
						} else {
							printf("Succeeded.\n");
						}
					}
				} else {
					throw new RuntimeException("Strange arguments for the --sync=unpublish option.");
				}
			} elseif(strpos($this->runtimeOptions['sync'], 'dump') === 0) {
				$args = explode('=', $this->runtimeOptions['sync']);
				if(count($args) == 2) {
					$filename = $args[1];
					$handle = fopen($filename, "w");
					foreach($removedGUIDs as $GUID) {
						$line = sprintf("%s\n", $GUID);
						$fwrite = fwrite($handle, $line);
						if($fwrite === false) {
							throw new RuntimeException("Problems writing to the dump file.");
						}
					}
					fclose($handle);
				} else {
					throw new RuntimeException("Strange arguments for the --sync=dump option.");
				}
			} else {
				throw new RuntimeException("Strange arguments for the --sync=? option.");
			}
		}
	}
}