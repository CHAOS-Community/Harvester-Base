<?php
namespace CHAOS\Harvester\Modes;
abstract class SetByReferenceMode extends Mode {
	/**
	 * Execute the harvester in the specified mode.
	 * @param string $reference Some reference to a set of external entities, usually a string or integer.
	 */
	public abstract function execute($reference);
}