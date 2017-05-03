<?php
namespace CHAOS\Harvester\Modes;
abstract class AllMode extends Mode {

	/**
	 * Execute the harvester in the specified all mode.
	 */
	public abstract function execute();


	public function shouldCleanUp() {
		return true;
	}

}
