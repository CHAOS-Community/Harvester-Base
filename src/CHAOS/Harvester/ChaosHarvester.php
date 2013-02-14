<?php
namespace CHAOS\Harvester;
use CHAOS\SessionRefreshingPortalClient;

use CHAOS\Harvester\Shadows\Shadow;
use CHAOS\Harvester\Filters\EmbeddedFilter;

use CHAOS\Portal\Client\PortalClient;

use \RuntimeException, \Exception, \SimpleXMLElement, \DOMDocument;

class ChaosHarvester {
	
	static function main($arguments = array()) {
		self::printLogo();
		$h = new ChaosHarvester($arguments);
		print("---------- Harvester successfully constructed ----------\n");
		$h->start();
	}
	
	/** @var SessionRefreshingPortalClient */
	protected $_chaos;
	
	/** @var array[string]string */
	protected $_chaosParameters;
	
	/** @var array[string]string */
	protected $_options;
	
	/** @var Mode[string] */
	protected $_modes;
	
	/** @var CHAOS\Harvester\Processors\Processor[string] */
	protected $_processors;
	
	/** @var ExternalClient[string] */
	protected $_externalClients;
	
	/** @var SimpleXMLElement */
	protected $_configuration;
	
	function __construct($arguments = array()) {
		$this->_options = self::extractOptionsFromArguments($arguments);
		
		// Check for the configuration option
		if(!key_exists('configuration', $this->_options)) {
			trigger_error("Fatal error: The configuration runtime argument was expected.", E_USER_ERROR);
			self::printUsage();
			exit;
		}
		
		// Use this configuration option to check for the file.
		$configurationFile = $this->_options['configuration'];
		if(!is_readable($configurationFile)) {
			trigger_error("Fatal error: The configuration file ($configurationFile) given as runtime argument is unreadable.", E_USER_ERROR);
			self::printUsage();
		}
		
		// Load the configuration file.
		$this->_configuration = simplexml_load_file($configurationFile, null, null, 'chc', true);
		if(!$this->validateConfiguration($this->_configuration)) {
			trigger_error("Fatal error: The configuration file given is invalid.");
			self::printUsage();
		}
		
		// Load variables from environment.
		$environmentTags = $this->_configuration->xpath('//*[@fromEnvironment]');
		foreach($environmentTags as $t) {
			/* @var $t SimpleXMLElement */
			$environmentVariable = strval($t['fromEnvironment']);
			if(key_exists($environmentVariable, $_SERVER)) {
				$t[0] = $_SERVER[$environmentVariable];
			} else {
				trigger_error(sprintf("The configuration file tells that an %s tag should be fetched from the %s environment variable, but this is not sat.", $t->getName(), $environmentVariable), E_USER_WARNING);
			}
		}
		
		// Notify which harvester was just started.
		self::info("Starting the harvester for the '%s' project of %s.", $this->_configuration->Project, $this->_configuration->Organisation);
		
		// Append include paths from configuration.
		$this->processIncludePath();
		
		// Reuse the case sensitive autoloader.
		require_once('CaseSensitiveAutoload.php');
		
		// Register this autoloader.
		spl_autoload_extensions(".php");
		spl_autoload_register("CaseSensitiveAutoload");
		
		// Require the timed lib to time actions.
		require_once('timed.php');
		timed(); // Tick tack, time is ticking.
		
		// Parsing external clients
		$this->_externalClients = array();
		foreach($this->_configuration->xpath("chc:ExternalClient") as $filter) {
			/* @var $filter SimpleXMLElement */
			$attributes = $filter->attributes();
			
			$name = strval($attributes->name);
			//var_dump($methodName);
			$namespace = strval($attributes->namespace);
			$className = strval($attributes->className);
			$parameters = $filter->xpath("chc:Parameter");
			$params = array();
			foreach($parameters as $parameter) {
				/* @var $p SimpleXMLElement */
				$parameterAttributes = $parameter->attributes();
				$params[strval($parameterAttributes->name)] = strval($parameter);
			}
			$this->loadExternalClient($name, $namespace, $className, $params);
			
			try {
				if(!$this->_externalClients[$name]->sanityCheck()) {
					throw new \RuntimeException("Unknown error during sanity check.");
				}
			} catch (RuntimeException $e) {
				throw new \RuntimeException("External client '$name' failed the sanity check.", null, $e);
			}
		}
		
		// Parsing chaos configurations
		$this->_chaosParameters = array();
		foreach($this->_configuration->xpath("chc:ChaosConfiguration/*") as $parameter) {
			/* @var $parameter SimpleXMLElement */
			$this->_chaosParameters[$parameter->getName()] = strval($parameter);
		}
		if(!key_exists('ClientGUID', $this->_chaosParameters) || strlen($this->_chaosParameters['ClientGUID']) == 0) {
			$this->_chaosParameters['ClientGUID'] = self::generateGUID();
		}
		$servicePath = $this->_chaosParameters['URL'];
		$this->info("Using CHAOS service: %s", $servicePath);
		$this->_chaos = new SessionRefreshingPortalClient($this, $servicePath, $this->_chaosParameters['ClientGUID']);
		
		$this->authenticateChaosSession();
		
		// Parsing modes.
		$this->_modes = array();
		foreach($this->_configuration->xpath("chc:Modes/chc:Mode") as $mode) {
			/* @var $mode SimpleXMLElement */
			$attributes = $mode->attributes();
			
			$name = strval($attributes->name);
			$type = strval($attributes->type);
			$namespace = strval($attributes->namespace);
			$className = strval($attributes->className);
			
			$parameters = $mode->xpath("chc:Parameter");
			$params = array();
			foreach($parameters as $parameter) {
				/* @var $p SimpleXMLElement */
				$parameterAttributes = $parameter->attributes();
				$params[strval($parameterAttributes->name)] = strval($parameter);
			}
			
			$this->loadMode($name, $type, $namespace, $className, $params);
		}
		
		// Parsing processors
		$this->_processors = array();
		foreach($this->_configuration->xpath("chc:Processors/chc:*") as $processor) {
			/* @var $processor SimpleXMLElement */
			$attributes = $processor->attributes();
				
			$name = strval($attributes->name);
			$type = $processor->getName();
			$namespace = strval($attributes->namespace);
			$className = strval($attributes->className);
			
			$parameters = $processor->xpath("chc:Parameter");
			$params = array();
			foreach($parameters as $parameter) {
				/* @var $p SimpleXMLElement */
				$parameterAttributes = $parameter->attributes();
				$params[strval($parameterAttributes->name)] = strval($parameter);
			}
			$this->loadProcessor($name, $type, $namespace, $className, $params);
			
			// Set the parameters which are specific to the processor.
			if($type === 'ObjectProcessor') {
				$objectTypeId = $processor->xpath('chc:ObjectTypeId');
				$this->_processors[$name]->setObjectTypeId(intval(strval($objectTypeId[0])));
				
				$folderId = $processor->xpath('chc:FolderId');
				$this->_processors[$name]->setFolderId(intval(strval($folderId[0])));
				
				$publishSettings = $processor->xpath('chc:PublishSettings');
				
				$publishSettings = $publishSettings[0];
				$this->_processors[$name]->setUnpublishEverywhere(strval($publishSettings->attributes()->UnpublishEverywhere) === 'true');
				
				$unpublishAccesspoints = $publishSettings->xpath('chc:UnpublishAccesspoint');
				$unpublishAccesspoints = array_map(function($accesspoint) { return trim($accesspoint); }, $unpublishAccesspoints);
				$this->_processors[$name]->setUnpublishAccesspointGUIDs($unpublishAccesspoints);
				
				$publishAccesspoints = $publishSettings->xpath('chc:PublishAccesspoint');
				$publishAccesspoints = array_map(function($accesspoint) { return trim($accesspoint); }, $publishAccesspoints);
				$this->_processors[$name]->setPublishAccesspointGUIDs($publishAccesspoints);
			} elseif($type === 'MetadataProcessor') {
				$validate = $processor->xpath('chc:validate');
				$this->_processors[$name]->setValidate(strval($validate[0]) == 'true');
				
				$schemaGUID = $processor->xpath('chc:schemaGUID');
				$schemaLocation = $processor->xpath('chc:schemaLocation');
				if(count($schemaGUID) == 1 && strlen(trim($schemaGUID[0])) > 0) {
					if(count($schemaLocation) == 1 && strlen(trim($schemaLocation[0])) > 0) {
						$this->_processors[$name]->fetchSchema(trim($schemaGUID[0]), trim($schemaLocation[0]));
					} else {
						$this->_processors[$name]->fetchSchema(trim($schemaGUID[0]));
					}
				}
			} elseif($type === 'FileProcessor') {
				$formatId = $processor->xpath('chc:FormatId');
				$this->_processors[$name]->setFormatId(intval(strval($formatId[0])));
				
				$destinationElements = $processor->xpath('chc:Destination');
				$destinations = array();
				foreach($destinationElements as $destination) {
					$destinations[] = array(
						"name" => strval($destination->attributes()->name),
						"id" => intval(array_pop($destination->xpath('chc:id'))),
						"baseURL" => strval(array_pop($destination->xpath('chc:baseURL')))
					);
				}
				$this->_processors[$name]->setDestinations($destinations);
			}
			
			// Parsing filters
			$filters = array();
			foreach($processor->xpath("chc:Filters/chc:Filter") as $filter) {
				/* @var $filter SimpleXMLElement */
				$filterAttributes = $filter->attributes();
					
				$filterName = strval($filterAttributes->name);
				//var_dump($methodName);
				$filterNamespace = strval($filterAttributes->namespace);
				$filterClassName = strval($filterAttributes->className);
				
				if(key_exists($filterName, $filters)) {
					throw new RuntimeException("A filter by the name of '$filterName' is already loaded.");
				} else {
					$filters[$filterName] = $this->loadFilter($filterName, $filterNamespace, $filterClassName);
				}
			}
			
			// Parsing the embedded filters.
			foreach($processor->xpath("chc:Filters/chc:EmbeddedFilter") as $filter) {
				/* @var $filter SimpleXMLElement */
				$filterAttributes = $filter->attributes();
					
				$filterName = strval($filterAttributes->name);
				$filterLanguage = strval($filterAttributes->language);
				if($filterLanguage != 'PHP') {
					trigger_error("Cannot use an embedded filter which is not written in PHP.", E_USER_WARNING);
				}
				
				if(key_exists($filterName, $filters)) {
					throw new RuntimeException("A filter by the name of '$filterName' is already loaded.");
				} else {
					$filterObject = $this->loadFilter($filterName, '\CHAOS\Harvester\Filters', 'EmbeddedFilter');
					
					$filters[$filterName] = $filterObject;
					/* @var $filterObject EmbeddedFilter */
					if($filterObject instanceof EmbeddedFilter) {
						$filterObject->setCode(strval($filter));
					} else {
						trigger_error("Error loading the filter named $filterName", E_USER_ERROR);
					}
				}
			}
			
			$this->_processors[$name]->setFilters($filters);
		}
	}
	
	protected static function extractOptionsFromArguments($arguments) {
		$result = array();
		for($i = 0; $i < count($arguments); $i++) {
			if(strpos($arguments[$i], '--') === 0) {
				$equalsIndex = strpos($arguments[$i], '=');
				if($equalsIndex === false) {
					$name = substr($arguments[$i], 2);
					$result[$name] = true;
				} else {
					$name = substr($arguments[$i], 2, $equalsIndex-2);
					$value = substr($arguments[$i], $equalsIndex+1);
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
	
	protected static function printUsage() {
		printf("Usage: --configuration=[Path to XML configuration file]\n");
	}
	
	protected static function printLogo() {
		echo " ______________                      \n";
		echo " __  ____/__  /_______ ______________\n";
		echo " _  /    __  __ \  __ `/  __ \_  ___/\n";
		echo " / /___  _  / / / /_/ // /_/ /(__  ) \n";
		echo " \____/  /_/ /_/\__,_/ \____//____/  \n";
		echo " Harvester v.0.2                     \n";
		echo "\n";
	}
	
	public static function generateGUID() {
		mt_srand((double)microtime()*10000); // optional for php 4.2.0 and up.
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45); // "-"
		$uuid = ''
			.substr($charid, 0, 8).$hyphen
			.substr($charid, 8, 4).$hyphen
			.substr($charid,12, 4).$hyphen
			.substr($charid,16, 4).$hyphen
			.substr($charid,20,12);
		return $uuid;
	}
	
	public function info() {
		$args = func_get_args();
		$args[0] = sprintf("[i] %s\n", $args[0]);
		call_user_func_array('printf', $args);
	}
	
	public function debug() {
		if(key_exists('debug', $this->_options)) {
			$args = func_get_args();
			$args[0] = sprintf("[d] %s\n", $args[0]);
			call_user_func_array('printf', $args);
		}
	}
	
	public function getOptions() {
		return $this->_options;
	}
	
	public function hasOption($name) {
		return key_exists($name, $this->_options);
	}
	
	public function getOption($name) {
		if($name == null || !key_exists($name, $this->_options)) {
			return null;
		} else {
			return $this->_options[$name];
		}
	}
	
	/**
	 * Validates the configuration
	 * @param SimpleXMLElement $configuration
	 * @return boolean True if the configuration is invalid, false otherwise.
	 */
	protected function validateConfiguration($configuration) {
		if($configuration == null || ! $configuration instanceof SimpleXMLElement) {
			throw new RuntimeException("Error parsing configuration.");
		}
		
		$schemaLocation = realpath(__DIR__ . '/../../../schemas/ChaosHarvesterConfiguration.xsd');
		
		// Validate the configuration file, against the schema.
		$dom_document = new DOMDocument();
		$dom_element = dom_import_simplexml($configuration);
		$dom_element = $dom_document->importNode($dom_element, true);
		$dom_element = $dom_document->appendChild($dom_element);
		
		if (!$dom_document->schemaValidate($schemaLocation)) {
			trigger_error("The configuration file was invalid.", E_USER_ERROR);
		} else {
			self::info("Configuration validated sucessfully against the schema.");
		}
		return true;
	}
	
	protected function processIncludePath() {
		foreach($this->_configuration->IncludePaths->path as $path) {
			$resolvedPath = $this->resolvePath($path);
			if($resolvedPath == null || !is_dir($resolvedPath)) {
				trigger_error("Include path '$path' relative to '".__DIR__."' is not a valid directory.", E_USER_ERROR);
			} else {
				set_include_path(get_include_path() . PATH_SEPARATOR . $resolvedPath);
			}
		}
	}
	
	/**
	 * Resolves a path to some filename or folder, possibly appending the BasePath of the configuration.
	 * @param string $path
	 * @return string|null An abstract 
	 */
	public function resolvePath($path) {
		$alternativePath = strval($this->_configuration->BasePath) . DIRECTORY_SEPARATOR . $path;
		if(is_file($path) || is_dir($path)) {
			return realpath($path);
		} elseif(is_file($alternativePath) || is_dir($alternativePath)) {
			return realpath($alternativePath);
		} else {
			return null;
		}
	}
	
	protected function loadClass($name, $namespace, $className, $requiredSuperclasses = array(), $requiredInterfaces = array(), $parameters = array()) {
		$requiredInterfaces[] = 'CHAOS\Harvester\Loadable';
		$class = $namespace . "\\" . $className;
		
		$parents = class_parents($class, true);
		foreach($requiredSuperclasses as $c) {
			if(!key_exists($c, $parents)) {
				throw new RuntimeException("Error loading class, $name at $class should extend $c");
			}
		}
		$interfaces = class_implements($class, true);
		foreach($requiredInterfaces as $i) {
			if(!key_exists($i, $interfaces)) {
				throw new RuntimeException("Error loading class, $name at $class should implement $i");
			}
		}
		// We came this far ..
		return new $class($this, $name, $parameters);
	}
	
	/**
	 * Loads a mode into the harvester.
	 * @param string $name
	 * @param string $type
	 * @param string $namespace
	 * @param string $className
	 * @return Mode|null The mode or null if the mode could not be loaded.
	 */
	protected function loadMode($name, $type, $namespace, $className, $parameters = null) {
		$modeInterface = sprintf('CHAOS\Harvester\Modes\%sMode', $type);
		$mode = $this->loadClass($name, $namespace, $className, array($modeInterface), array(), $parameters);
		if(key_exists($name, $this->_modes)) {
			throw new RuntimeException("A mode by the name of '$name' is already loaded.");
		} else {
			$this->_modes[$name] = $mode;
		}
	}
	
	protected function loadProcessor($name, $type, $namespace, $className, $parameters = null) {
		$processorSuperclass = sprintf('CHAOS\Harvester\Processors\%s', $type);
		$processor = $this->loadClass($name, $namespace, $className, array($processorSuperclass), array(), $parameters);
		if(key_exists($name, $this->_processors)) {
			throw new RuntimeException("A processor by the name of '$name' is already loaded.");
		} else {
			$this->_processors[$name] = $processor;
		}
	}
	
	protected function loadFilter($name, $namespace, $className) {
		return $this->loadClass($name, $namespace, $className, array('CHAOS\Harvester\Filters\Filter'));
	}
	
	protected function loadExternalClient($name, $namespace, $className, $parameters) {
		$externalClient = $this->loadClass($name, $namespace, $className, array(), array('CHAOS\Harvester\IExternalClient'), $parameters);
		if(key_exists($name, $this->_externalClients)) {
			throw new RuntimeException("An external client by the name of '$name' is already loaded.");
		} else {
			$this->_externalClients[$name] = $externalClient;
		}
	}
	
	/**
	 * Authenticate the Chaos session using the environment variables for email and password.
	 * @throws \RuntimeException If the authentication fails.
	 */
	public function authenticateChaosSession() {
		self::info("Authenticating the session using email %s.", $this->_chaosParameters['Email']);
		$result = $this->_chaos->EmailPassword()->Login($this->_chaosParameters['Email'], $this->_chaosParameters['Password']);
		if(!$result->WasSuccess()) {
			throw new \RuntimeException("Couldn't authenticate the session, error in request: ".$result->Error()->Message());
		} elseif(!$result->EmailPassword()->WasSuccess()) {
			throw new \RuntimeException("Couldn't authenticate the session, please check the CHAOS_EMAIL and CHAOS_PASSWORD parameters: ".$result->EmailPassword()->Error()->Message());
		} else {
			self::info("Chaos session was successfully authenticated: %s", $this->_chaos->SessionGUID());
		}
	}
	
	/**
	 * @return \CHAOS\Portal\Client\IPortalClient The current chaos client.
	 */
	public function getChaosClient() {
		return $this->_chaos;
	}
	
	/**
	 * Starts the harvester in a selected mode, this mode has to match a name in the given configuration file.
	 * @param string $mode The name of the mode to start the harvester in.
	 */
	public function start($mode = null) {
		// Load from options if not sat as an argument for the function.
		if($mode == null && key_exists('mode', $this->_options)) {
			$mode = $this->_options['mode'];
		}
		
		if(key_exists($mode, $this->_modes)) {
			self::info("Starting harvester in '%s' mode.", $mode);
			if($this->_modes[$mode] instanceof Modes\AllMode) {
				
				// Execute the mode!
				$this->_modes[$mode]->execute();
				
			} else if($this->_modes[$mode] instanceof Modes\SingleByReferenceMode || $this->_modes[$mode] instanceof Modes\SetByReferenceMode) {
				if(!key_exists('reference', $this->_options)) {
					trigger_error('You have to specify a --reference={reference} in the '.$mode.' mode.', E_USER_ERROR);
				}
				
				// Execute the mode!
				$reference = $this->_options['reference'];
				$this->_modes[$mode]->execute($reference);
				
			} else {
				throw new RuntimeException("Mode type is not supported.");
			}
		} else {
			throw new RuntimeException("Mode '$mode' is not supported, please choose from: ".implode(', ', array_keys($this->_modes)));
		}
		
		$this->printProcessingExceptions();
		
		echo "\n";
		echo "All done - ";
		timed_print();
	}
	
	/**
	 * An array describing the processing exceptions thrown during any processing.
	 * @var array[]
	 */
	protected $processingExceptions = array();
	
	public function printProcessingExceptions() {
		$total = count($this->processingExceptions);
		if($total > 0) {
			$this->info("Printing a summary of %u exceptions, thrown while processing:", $total);	
			$e = 1;
			foreach($this->processingExceptions as $exception) {
				$traceString = implode("\n\t", explode("\n", $exception['exception']->getTraceAsString()));
				if(strlen($exception['externalObject']) > 0) {
					$title = strval($exception['externalObject']);
				} elseif(strlen($exception['shadow']) > 0) {
					$title = strval($exception['shadow']);
				} else {
					$title = "[some unnamed object]";
				}
				if($exception['processorName'] !== null) {
					$this->info("[$e/$total] Error '%s' when processing %s with the '%s' processor.\n\t%s", $exception['exception']->getMessage(), $title, $exception['processorName'], $traceString);
				} else {
					$this->info("[$e/$total] Error '%s' when processing %s.\n\t%s", $exception['exception']->getMessage(), $title, $traceString);
				}
				$e++;
			}
		} else {
			$this->info("No exceptions was thrown while processing.");
		}
	}
	
	public function registerProcessingException($exception, $externalObject, $shadow, $processorName = null) {
		trigger_error(sprintf("%s\n%s", $exception->getMessage(), $exception->getTraceAsString()), E_USER_WARNING);
		$this->processingExceptions[] = array(
				"exception" => $exception,
				"externalObject" => $externalObject,
				"shadow" => $shadow,
				"processorName" => $processorName
		);
	}
	
	/**
	 * Invoke a registered processor.
	 * @param string $processorName The name of the processor to invoke.
	 * @param unknown_type $externalObject The external object from the external service.
	 * @param \CHAOS\Harvester\Shadows\Shadow $shadow The shadow to build upon.
	 * @return \CHAOS\Harvester\Shadows\Shadow The resulting shadow.
	 */
	public function process($processorName, $externalObject, $shadow = null) {
		if($processorName == null || strlen($processorName) == 0) {
			throw new RuntimeException("A processor name has to be choosen.");
		} elseif(!key_exists($processorName, $this->_processors)) {
			throw new RuntimeException("No processor named $processorName loaded.");
		} else {
			$this->debug("Processing the external object with the '%s' processor.", $processorName);
			$processor = $this->_processors[$processorName];
			/* @var $processor \CHAOS\Harvester\Processors\Processor */
			$filterResult = $processor->passesFilters($externalObject);
			try {
				if($filterResult === true) {
					return $processor->process($externalObject, $shadow);
				} elseif(is_array($filterResult)) {
					foreach($filterResult as $rejection) {
						if($rejection['reason'] === false || strlen($rejection['reason']) == 0) {
							$this->info("Skipped because the external object didn't pass the %s filter without a reason.", $rejection['name']);
						} else {
							$this->info("Skipped because the external object didn't pass the %s filter, because: %s", $rejection['name'], $rejection['reason']);
						}
					}
					return $processor->skip($externalObject, $shadow);
				}
			} catch(Exception $exception) {
				$this->registerProcessingException($exception, $externalObject, $shadow, $processorName);
				// Do nothing then ...
				return $shadow;
			}
		}
	}
	
	public function getExternalClient($name) {
		if(key_exists($name, $this->_externalClients)) {
			return $this->_externalClients[$name];
		} else {
			return null;
		}
	}
}
ChaosHarvester::main($_SERVER['argv']);