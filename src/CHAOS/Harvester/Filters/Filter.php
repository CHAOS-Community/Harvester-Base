<?php
namespace CHAOS\Harvester\Filters;
abstract class Filter {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $harvester;
	
	/**
	 * Does this external object pass the filter?
	 * @return boolean|string True if it passes, false if indicating that it should be skipped, string if a message is attached.
	 */
	public abstract function passes($externalObject);
}