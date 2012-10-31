<?php
namespace CHAOS\Harvester\Shadows;
use \RuntimeException;
class SkippedObjectShadow extends ObjectShadow {
	function __construct() {
		$this->skipped = true;
	}
}