<?php
/**
 * This abstract harvester copies DKA information into a CHAOS service.
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    $Id:$
 * @link       https://github.com/CHAOS-Community/Harvester-Base
 * @since      File available since Release 0.1
 */

/**
 * An abstract harvester.
 *
 * @author     Kræn Hansen (Open Source Shift) for the danish broadcasting corporation, innovations.
 * @license    http://opensource.org/licenses/LGPL-3.0	GNU Lesser General Public License
 * @version    Release: @package_version@
 * @link       https://github.com/CHAOS-Community/Harvester-Base
 * @since      Class available since Release 0.1
 */
abstract class ADKACHAOSHarvester extends ACHAOSHarvester {
	
	const DKA_OBJECT_TYPE_NAME = "DKA Program";
	
	/**
	 * The DFI client to be used for communication with the DFI Service. 
	 * @var DFIClient
	 */
	public $_dfi;
	
	protected $_DKAObjectType;
	
	/**
	 * Fetches the DKA Program object type and stores it in the _DKAObjectType field.
	 * @throws RuntimeException If it fails.
	 */
	protected function CHAOS_fetchObjectType() {
		printf("Looking up the DKA Program type: ");
		
		try {
			$this->_DKAObjectType = $this->CHAOS_fetchObjectTypeFromName(self::DKA_OBJECT_TYPE_NAME);
			if($this->_DKAObjectType === null) {
				printf("Failed.\n");
			} else {
				printf("Succeeded, it has ID: %s\n", $this->_DKAObjectType->ID);
			}
		} catch(Exception $e) {
			printf("Failed.\n");
			throw new RuntimeException("Couldn't lookup the DKA object type for the DKA specific data.");
		}
	}
}