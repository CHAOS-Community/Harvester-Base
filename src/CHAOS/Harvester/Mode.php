<?php
namespace CHAOS\Harvester;
abstract class Mode implements Loadable {
	
	/**
	 * A reference to the harvester.
	 * @var \CHAOS\Harvester\ChaosHarvester
	 */
	protected $_harvester;
	
	/**
	 * Execute the harvester in the specified mode.
	 */
	public abstract function execute();
	
}