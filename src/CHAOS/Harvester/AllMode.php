<?php
namespace CHAOS\Harvester;
abstract class AllMode extends Mode {
	
	/**
	 * Execute the harvester in the specified mode.
	 */
	public abstract function execute();
	
}