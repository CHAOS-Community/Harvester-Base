<?php
namespace CHAOS\Harvester\Modes;
abstract class Mode implements \CHAOS\Harvester\Loadable {

	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_harvester;
	protected $_name;

	public function __construct($harvester, $name, $parameters) {
		$this->_harvester = $harvester;
		$this->_name = $name;
	}

	/**
	 * Should the harvester perform its clean up after running this mode?
	 * @return bool True if clean up should be performed, false otherwise.
	 */
	public abstract function shouldCleanUp();

}
