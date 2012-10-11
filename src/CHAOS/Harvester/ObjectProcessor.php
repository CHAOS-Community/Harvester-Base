<?php
namespace CHAOS\Harvester;
abstract class ObjectProcessor extends Processor {
	/**
	 * The object type id of the objects that this object processor produces.
	 * @var integer The object type ID to use when creating object shadows.
	 */
	protected $_objectTypeId;
	
	/**
	 * Sets the object type id of the objects that this object processor produces.
	 * @param integer $typeID The type ID to use
	 */
	function setObjectTypeId($objectTypeId) {
		$this->_objectTypeId = $objectTypeId;
	}
	/**
	 * The object type id of the objects that this object processor produces.
	 * @var integer The object type ID to use when creating object shadows.
	 */
	protected $_folderId;
	
	/**
	 * Sets the object type id of the objects that this object processor produces.
	 * @param integer $folderId The folder ID to use.
	 */
	function setFolderId($folderId) {
		$this->_folderId = $folderId;
	}
}