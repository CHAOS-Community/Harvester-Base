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
	 */
	public abstract function process($externalObject);
}