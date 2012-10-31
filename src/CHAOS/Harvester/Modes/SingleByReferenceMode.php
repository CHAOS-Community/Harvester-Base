<?php
namespace CHAOS\Harvester\Modes;
abstract class SingleByReferenceMode extends Mode {
	/**
	 * Execute the harvester in the specified mode.
	 * @param string $reference Some reference to an external entity, usually a string or integer.
	 */
	public abstract function execute($reference);
}