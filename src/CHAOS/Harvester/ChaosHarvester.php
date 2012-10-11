<?php
namespace CHAOS\Harvester;
use \RuntimeException, \SimpleXMLElement, \DOMDocument;

class ChaosHarvester {
	
	static function main($arguments = array()) {
		self::printLogo();
		$h = new ChaosHarvester($arguments);
		print("---------- Harvester successfully constructed ----------\n");
		$h->start();
	}
	
	//const CHC_NAMESPACE = 'http://www.example.org/ChaosHarvesterConfiguration';
	const CHC_SCHEMA_ENVVAR = 'CHC_SCHEMA';
	
	/** @var \CHAOS\Portal\Client\PortalClient */
	protected $_chaos;
	
	/** @var array[string]string */
	protected $_chaosParameters;
	
	/** @var array[string]string */
	protected $_options;
	
	/** @var Mode[string] */
	protected $_modes;
	
	/** @var Processor[string] */
	protected $_processors;
	
	/** @var Filter[string] */
	protected $_filters;
	
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
		
		// Parsing modes.
		$this->_modes = array();
		foreach($this->_configuration->xpath("chc:Modes/chc:Mode") as $mode) {
			/* @var $mode SimpleXMLElement */
			$attributes = $mode->attributes();
			
			$name = strval($attributes->name);
			$type = strval($attributes->type);
			$namespace = strval($attributes->namespace);
			$className = strval($attributes->className);
			$this->loadMode($name, $type, $namespace, $className);
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
			$this->loadProcessor($name, $type, $namespace, $className);
		}
		
		// Parsing filters
		$this->_filters = array();
		foreach($this->_configuration->xpath("chc:Filters/chc:Filter") as $filter) {
			/* @var $filter SimpleXMLElement */
			$attributes = $filter->attributes();
			
			$name = strval($attributes->name);
			//var_dump($methodName);
			$namespace = strval($attributes->namespace);
			$className = strval($attributes->className);
			$this->loadFilter($name, $namespace, $className);
		}
		
		// Parsing the embedded filters.
		foreach($this->_configuration->xpath("chc:Filters/chc:EmbeddedFilter") as $filter) {
			/* @var $filter SimpleXMLElement */
			$attributes = $filter->attributes();
			
			$name = strval($attributes->name);
			$language = strval($attributes->language);
			if($language != 'PHP') {
				trigger_error("Cannot use an embedded filter which is not written in PHP.", E_USER_WARNING);
			}
			$this->loadFilter($name, '\CHAOS\Harvester', 'EmbeddedFilter');
			
			$filterObject = $this->_filters[$name];
			/* @var $filterObject EmbeddedFilter */
			if($filterObject instanceof EmbeddedFilter) {
				$filterObject->setCode(strval($filter));
			}
		}
		
		// Parsing external clients
		$this->_externalClients = array();
		foreach($this->_configuration->xpath("chc:ExternalClient") as $filter) {
			/* @var $filter SimpleXMLElement */
			$attributes = $filter->attributes();
			
			$name = strval($attributes->name);
			//var_dump($methodName);
			$namespace = strval($attributes->namespace);
			$className = strval($attributes->className);
			$this->loadExternalClient($name, $namespace, $className);
			
			$parameters = $filter->xpath("chc:Parameter");
			$params = array();
			foreach($parameters as $parameter) {
				/* @var $p SimpleXMLElement */
				$parameterAttributes = $parameter->attributes();
				$params[strval($parameterAttributes->name)] = strval($parameter);
			}
			$this->_externalClients[$name]->setParameters($params);
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
		$this->_chaos = new \CHAOS\Portal\Client\PortalClient($this->_chaosParameters['URL'], $this->_chaosParameters['ClientGUID']);
		$this->authenticateChaosSession();
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
	
	public static function info() {
		$args = func_get_args();
		$args[0] = sprintf("[i] %s\n", $args[0]);
		call_user_func_array('printf', $args);
	}
	
	public static function debug() {
		$args = func_get_args();
		$args[0] = sprintf("[d] %s\n", $args[0]);
		call_user_func_array('printf', $args);
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
	
	/**
	 * Validates the configuration
	 * @param SimpleXMLElement $configuration
	 * @return boolean True if the configuration is invalid, false otherwise.
	 */
	protected function validateConfiguration($configuration) {
		if($configuration == null || ! $configuration instanceof SimpleXMLElement) {
			throw new RuntimeException("Error parsing configuration.");
		}
		/*
		if(key_exists(self::CHC_NAMESPACE, $configuration->getDocNamespaces())) {
			throw new RuntimeException("Configuration does not reference the CHC namespace.");
		}
		*/
		if(key_exists(self::CHC_SCHEMA_ENVVAR, $_SERVER)) {
			$schemaLocation = $_SERVER[self::CHC_SCHEMA_ENVVAR];
			if(strlen($schemaLocation) > 0) {
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
			}
		} else {
			self::warning(sprintf('Warning: The %s environment variable is not sat - cannot validate configuration file.', self::CHC_SCHEMA_ENVVAR));
		}
		return true;
	}
	
	protected function processIncludePath() {
		foreach($this->_configuration->IncludePaths->path as $path) {
			$path = strval($path);
			if(!is_dir($path)) {
				trigger_error('Include path '.$path.' relative to '.__DIR__.' is not a valid directory.', E_USER_ERROR);
			} else {
				set_include_path(get_include_path() . PATH_SEPARATOR . $path);
			}
		}
	}
	
	protected function loadClass($name, $namespace, $className, $requiredSuperclasses = array(), $requiredInterfaces = array()) {
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
		return new $class($this, $name);
	}
	
	/**
	 * Loads a mode into the harvester.
	 * @param string $name
	 * @param string $type
	 * @param string $namespace
	 * @param string $className
	 * @return Mode|null The mode or null if the mode could not be loaded.
	 */
	protected function loadMode($name, $type, $namespace, $className) {
		$modeInterface = sprintf('CHAOS\Harvester\%sMode', $type);
		$mode = $this->loadClass($name, $namespace, $className, array($modeInterface));
		if(key_exists($name, $this->_modes)) {
			throw new RuntimeException("A mode by the name of '$name' is already loaded.");
		} else {
			$this->_modes[$name] = $mode;
		}
	}
	
	protected function loadProcessor($name, $type, $namespace, $className) {
		$processorSuperclass = sprintf('CHAOS\Harvester\%s', $type);
		$processor = $this->loadClass($name, $namespace, $className, array($processorSuperclass));
		if(key_exists($name, $this->_processors)) {
			throw new RuntimeException("A processor by the name of '$name' is already loaded.");
		} else {
			$this->_processors[$name] = $processor;
		}
	}
	
	protected function loadFilter($name, $namespace, $className) {
		$filter = $this->loadClass($name, $namespace, $className, array('CHAOS\Harvester\Filter'));
		if(key_exists($name, $this->_filters)) {
			throw new RuntimeException("A filter by the name of '$name' is already loaded.");
		} else {
			$this->_filters[$name] = $filter;
		}
	}
	
	protected function loadExternalClient($name, $namespace, $className) {
		$externalClient = $this->loadClass($name, $namespace, $className, array(), array('CHAOS\Harvester\IExternalClient'));
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
			throw new \RuntimeException("Couldn't authenticate the session, error in request.");
		} elseif(!$result->EmailPassword()->WasSuccess()) {
			throw new \RuntimeException("Couldn't authenticate the session, please check the CHAOS_EMAIL and CHAOS_PASSWORD parameters.");
		} else {
			self::info("Chaos session was successfully authenticated.");
		}
	}
	
	public function getChaosClient() {
		return $this->_chaos;
	}
	
	public function start($mode = null) {
		// Load from options if not sat as an argument for the function.
		if($mode == null && key_exists('mode', $this->_options)) {
			$mode = $this->_options['mode'];
		}
		
		if(key_exists($mode, $this->_modes)) {
			self::info("Starting harvester in '%s' mode.", $mode);
			// This mode is supported.
			$this->_modes[$mode]->execute();
		} else {
			throw new RuntimeException("Mode '$mode' is not supported, please choose from: ".implode(', ', array_keys($this->_modes)));
		}
	}
	
	public function process($externalObject) {
		$filterResult = $this->passesFilters($externalObject);
		if($filterResult === true) {
			$this->debug("Starting to process external object with %d different processors.", count($this->_processors));
			$p = 1;
			foreach($this->_processors as $name => $processor) {
				$this->debug("Processing the external object with the '%s' processor %d/%d", $name, $p, count($this->_processors));
				/* @var $processor Processor */
				$processor->process($externalObject);
				$p++;
			}
		}
	}
	
	public function passesFilters($externalObject) {
		$finalResult = array();
		foreach($this->_filters as $name => $f) {
			/* @var $f Filter */
			$result = $f->passes($externalObject);
			if($result !== true) {
				$finalResult[] = array('name' => $name, 'filter' => $f, 'problem' => $result);
			}
		}
		if(count($finalResult) == 0) {
			return true;
		} else {
			return $finalResult;
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