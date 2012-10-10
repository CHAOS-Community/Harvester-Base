<?php
class ChaosHarvester {
	
	static function main($arguments = array()) {
		self::printLogo();
		$h = new ChaosHarvester($arguments);
	}
	
	const CHC_NAMESPACE = 'http://www.example.org/ChaosHarvesterConfiguration';
	const CHC_SCHEMA_ENVVAR = 'CHC_SCHEMA';
	
	protected $options;
	
	/** @var SimpleXMLElement */
	protected $configuration;
	
	function __construct($arguments = array()) {
		$this->options = self::extractOptionsFromArguments($arguments);
		
		if(!key_exists('configuration', $this->options)) {
			self::error("Fatal error: The configuration runtime argument was expected.");
			self::printUsage();
			exit;
		}
		
		$configurationFile = $this->options['configuration'];
		if(!is_readable($configurationFile)) {
			self::error("Fatal error: The configuration file given as runtime argument is unreadable.");
			self::printUsage();
			exit;
		}
		
		$this->configuration = simplexml_load_file($configurationFile, null, null, self::CHC_NAMESPACE);
		if(!$this->validateConfiguration($this->configuration)) {
			self::error("Fatal error: The configuration file given is invalid.");
			self::printUsage();
			exit;
		}
		printf("Starting the harvester for the '%s' project of %s.\n", $this->configuration->Project, $this->configuration->Organisation);
		
		// Load variables from environment.
		$environmentTags = $this->configuration->xpath('//*[@fromEnvironment]');
		foreach($environmentTags as $t) {
			/* @var $t SimpleXMLElement */
			$environmentVariable = strval($t['fromEnvironment']);
			if(key_exists($environmentVariable, $_SERVER)) {
				$t[0] = $_SERVER[$environmentVariable];
			} else {
				self::warning(sprintf("Warning: The configuration file tells that an %s tag should be fetched from the %s environment variable, but this is not sat.", $t->getName(), $environmentVariable));
			}
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
		echo "______________                      \n";
		echo "__  ____/__  /_______ ______________\n";
		echo "_  /    __  __ \  __ `/  __ \_  ___/\n";
		echo "/ /___  _  / / / /_/ // /_/ /(__  ) \n";
		echo "\____/  /_/ /_/\__,_/ \____//____/  \n";
		echo "Harvester v.0.2                     \n";
		echo "\n";
	}
	
	protected static function error($message = "") {
		printf ("[E] %s\n", $message);
	}
	
	protected static function info($message = "") {
		printf ("[i] %s\n", $message);
	}
	
	protected static function warning($message = "") {
		printf ("[w] %s\n", $message);
	}
	
	protected static function debug($message = "") {
		printf ("[d] %s\n", $message);
	}
	
	/**
	 * Validates the configuration
	 * @param SimpleXMLElement $configuration
	 * @return boolean True if the configuration is invalid, false otherwise.
	 */
	protected function validateConfiguration($configuration) {
		if($configuration == null) {
			throw new RuntimeException("Error parsing configuration.");
		}
		if(key_exists(self::CHC_NAMESPACE, $configuration->getDocNamespaces())) {
			throw new RuntimeException("Configuration does not reference the CHC namespace.");
		}
		if(key_exists(self::CHC_SCHEMA_ENVVAR, $_SERVER)) {
			$schemaLocation = $_SERVER[self::CHC_SCHEMA_ENVVAR];
			if(strlen($schemaLocation) > 0) {
				// Validate the configuration file, against the schema.
				/*
				$xml = new DOMDocument(); 
				$xml->load('./lures.xml');
				
				if (!$xml->schemaValidate('./lures.xsd')) { 
				   echo "invalid<p/>";
				} 
				else { 
				   echo "validated<p/>"; 
				}
				*/
			}
		} else {
			self::warning(sprintf('Warning: The %s environment variable is not sat - cannot validate configuration file.', self::CHC_SCHEMA_ENVVAR));
		}
		return true;
	}
}
ChaosHarvester::main($_SERVER['argv']);