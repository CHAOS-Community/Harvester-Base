<?php
namespace CHAOS\Harvester\Processors;

class PassThroughObjectProcessor extends ObjectProcessor {
	public function generateQuery($externalObject) {
		throw new \RuntimeException('GenerateQuery not implemented for PassThroughObjectProcessor');
	}


	public function process(&$externalObject, &$shadow = null) {
		// Pass through
	}
}