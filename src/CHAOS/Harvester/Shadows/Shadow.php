<?php
namespace CHAOS\Harvester\Shadows;
abstract class Shadow {
	/**
	 * Commit the shadow to the chaos service.
	 * @param CHAOS\Harvester\ChaosHarvester $harvester The harvester used to create this shadow. Get the chaos client from this.
	 * @param unknown_type $parent The parent shadow, for files, metadatas and related objects this is the subject object.
	 * @return unknown_type The chaos representation of the shadow.
	 */
	public abstract function commit($harvester, $parent = null);
}