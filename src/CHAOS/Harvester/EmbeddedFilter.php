<?php
namespace CHAOS\Harvester;
class EmbeddedFilter extends Filter implements \CHAOS\Harvester\Loadable {
	
	public function __construct($harvester, $name) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function passes($externalObject) {
		$this->_harvester->debug(__CLASS__." is processing.");
		return call_user_func($this->_function, $externalObject);
	}
	
	protected $_function;
	
	public function setCode($code) {
		$this->_function = create_function('$externalObject', $code);
	}
}