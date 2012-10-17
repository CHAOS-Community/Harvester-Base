<?php
namespace CHAOS\Harvester;
interface Loadable {
	
	/**
	 * Constructs the Loadable.
	 * @param \CHAOS\Harvester\ChaosHarvester $harvester
	 * @param string $name The name of the Loadable in the harvester.
	 */
	public function __construct($harvester, $name, $parameters);
	
}