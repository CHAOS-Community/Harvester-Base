<?php
namespace CHAOS\Harvester\Processors;
abstract class Processor implements \CHAOS\Harvester\Loadable {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_harvester;
	
	/**
	 * The name of the processor.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_name;
	
	/**
	 * Constructs a processor.
	 * @param \CHAOS\Harvester\ChaosHarvester $harvester a reference to the harvester which is going to ask this processor.
	 * @param string $name The name of this processor, from the configuration.
	 * @param string[string] $parameters An array of key => value parameters, from the configuration.
	 */
	public function __construct($harvester, $name, $parameters = null) {
		$this->_harvester = $harvester;
		$this->_name = $name;
		$this->setParameters($parameters);
	}
	
	/**
	 * Process a specific external object with the processor.
	 * @param unknown_type $externalObject The external object from the external service.
	 * @param Shadow $shadow The shadow object that has to be build up.
	 */
	public abstract function process($externalObject, &$shadow = null);
	
	/**
	 * Produce a shadow object which represents that this particular external object should not be processed.
	 * NOTE: This should be overwritten by concrete implementations.
	 * @param unknown_type $externalObject The external object from the external service.
	 * @param Shadow $shadow The shadow object that has to be build up.
	 */
	public function skip($externalObject, &$shadow = null) {
		throw new \Exception("Was asked to skip an external object for a processing with a processor, but the skip method of the processor wasn't implemented.");
		return $shadow;
	}
	
	/**
	 * An array of filters to apply before the processor is invoked.
	 * @var Filter[]
	 */
	protected $_filters;
	
	public function setFilters($filters) {
		$this->_filters = $filters;
	}
	
	/**
	 * An array of custom parameters.
	 * @var string[]
	 */
	protected $_parameters;
	
	/**
	 * Set the array of custom parameters.
	 * @param string[] $parameters An array of custom parameters.
	 */
	public function setParameters($parameters) {
		$this->_parameters = $parameters;
	}
	
	/**
	 * Does the given external object pass the filters registered for this processor?
	 * @param unknown_type $externalObject
	 */
	public function passesFilters($externalObject) {
		$finalResult = array();
		foreach($this->_filters as $name => $f) {
			/* @var $f Filter */
			$result = $f->passes($externalObject);
			if($result !== true && $result !== null) {
				$finalResult[] = array('name' => $name, 'filter' => $f, 'reason' => $result);
			}
		}
		if(count($finalResult) == 0) {
			return true;
		} else {
			return $finalResult;
		}
	}
}