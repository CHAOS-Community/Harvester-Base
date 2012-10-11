<?php
namespace CHAOS\Harvester;
abstract class SingleByReferenceMode extends Mode {
	/**
	 * Execute the harvester in the specified mode.
	 */
	public abstract function execute($reference);
}