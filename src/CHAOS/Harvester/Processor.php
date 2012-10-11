<?php
namespace CHAOS\Harvester;
abstract class Processor {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_harvester;
	
	/**
	 * Process a specific external object with the processor.
	 * @param unknown_type $externalObject The external object from the external service.
	 * @param Shadow $shadow The shadow object that has to be build up.
	 */
	public abstract function process($externalObject, $shadow = null);
	
	/**
	 * An array of filters to apply before the processor is invoked.
	 * @var Filter[]
	 */
	protected $_filters;
	
	public function setFilters($filters) {
		$this->_filters = $filters;
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
			if($result !== true) {
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