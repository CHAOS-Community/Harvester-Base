<?php
namespace CHAOS\Harvester\Processors;
use CHAOS\Harvester\Shadows\ObjectShadow;

abstract class PreProcessor extends Processor {

	/**
	 * (non-PHPdoc)
	 * @see \CHAOS\Harvester\Processors\Processor::process()
	 * @return void
	 */
	public abstract function process(&$externalObject, &$shadow = null);
}