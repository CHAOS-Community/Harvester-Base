<?php
namespace CHAOS\Harvester\Processors;
use CHAOS\Harvester\Shadows\ObjectShadow;

abstract class ObjectProcessor extends Processor {
	
	/**
	 * This method generates a Solr Query as a string, which when queried for should return the externalObjects representation in the CHAOS service.
	 * @param unknown $externalObject
	 */
	protected abstract function generateQuery($externalObject);
	
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
	
	/**
	 * If set, and an object is skipped, this is object is unpublished from any accesspoint it is published to.
	 * @var boolean
	 */
	protected $_unpublishEverywhere;
	
	/**
	 * If set, and an object is skipped, this is object is unpublished from any accesspoint it is published to.
	 * @param boolean $unpublishEverywhere
	 */
	function setUnpublishEverywhere($unpublishEverywhere) {
		$this->_unpublishEverywhere = $unpublishEverywhere;
	}
	
	/**
	 * If an object is skipped, this is object is unpublished from any accesspoint in the array.
	 * @var string[]
	 */
	protected $_unpublishAccesspointGUIDs = array();
	
	/**
	 * If an object is skipped, this is object is unpublished from any accesspoint in the array.
	 * @param string[] $unpublishEverywhere
	 */
	function setUnpublishAccesspointGUIDs($unpublishAccesspointGUIDs) {
		$this->_unpublishAccesspointGUIDs = $unpublishAccesspointGUIDs;
	}
	
	/**
	 * If an object is processed, this is object is published from any accesspoint in the array.
	 * @var string[]
	 */
	protected $_publishAccesspointGUIDs = array();
	
	/**
	 * If an object is processed, this is object is published from any accesspoint in the array.
	 * @param string[] $publishAnywhere
	 */
	function setPublishAccesspointGUIDs($publishAccesspointGUIDs) {
		$this->_publishAccesspointGUIDs = $publishAccesspointGUIDs;
	}
	
	/**
	 * Initializes a shadow with the information known by the processor, from the configuration.
	 * This method reads the skipped filed of the object shadow, so set this before calling this method.
	 * @param ObjectShadow $shadow The object shadow, that should be initalized
	 * @return ObjectShadow The initialized shadow.
	 */
	function initializeShadow($externalObject, &$shadow) {
		if($this->_unpublishEverywhere === true) {
			$this->_harvester->debug("If the object is skipped, it will be unpublished from every accesspoint.");
			$shadow->unpublishEverywhere = $this->_unpublishEverywhere;
		} else {
			foreach($this->_unpublishAccesspointGUIDs as $guid) {
				// $this->_harvester->debug("This object will be unpublished from accesspoint # $guid");
				$shadow->unpublishAccesspointGUIDs[] = $guid;
			}
		}
		foreach($this->_publishAccesspointGUIDs as $guid) {
			// $this->_harvester->debug("This object will be published to accesspoint # $guid");
			$shadow->publishAccesspointGUIDs[] = $guid;
		}
		$shadow->objectTypeId = $this->_objectTypeId;
		$shadow->folderId = $this->_folderId;
		$shadow->query = $this->generateQuery($externalObject);
		return $shadow;
	}
	
	function skip($externalObject, &$shadow = null) {
		$shadow = new ObjectShadow();
		$shadow->skipped = true;
		$shadow = $this->initializeShadow($externalObject, $shadow);
		
		$shadow->commit($this->_harvester);
		
		return $shadow;
	}
}