<?php
namespace CHAOS\Harvester\Shadows;
abstract class Shadow {
	public abstract function commit($harvester);
}