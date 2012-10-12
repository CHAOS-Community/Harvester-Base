<?php
namespace CHAOS\Harvester\Modes;
abstract class Mode implements \CHAOS\Harvester\Loadable {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_harvester;
	
}