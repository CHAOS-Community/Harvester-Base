<?php
namespace CHAOS\Harvester\Filters;
abstract class Filter implements \CHAOS\Harvester\Loadable {
	
	/**
	 * Constructs a new filter.
	 * @param \CHAOS\Harvester\ChaosHarvester $harvester a reference to the harvester which is going to ask this filter.
	 * @param string $name The name of the filter, from the configuration.
	 * @param string[string] $parameters An array of key => value parameters, from the configuration. 
	 */
	public function __construct($harvester, $name, $parameters = array()) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
		$this->_name = $name;
	}
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_harvester;
	
	/**
	 * The name of the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_name;
	
	/**
	 * Does this external object pass the filter?
	 * @return boolean|string True if it passes, false if indicating that it should be skipped, string if a message is attached.
	 */
	public abstract function passes($externalObject);
}