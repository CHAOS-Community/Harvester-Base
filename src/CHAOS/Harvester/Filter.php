<?php
namespace CHAOS\Harvester;
abstract class Filter {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $harvester;
	
	/**
	 * Does this external object pass the filter?
	 * @return boolean True if it passes, false if not indicating that it should be skipped.
	 */
	public abstract function passes($externalObject);
}