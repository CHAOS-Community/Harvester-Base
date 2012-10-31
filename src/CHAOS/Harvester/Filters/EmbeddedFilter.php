<?php
namespace CHAOS\Harvester\Filters;
class EmbeddedFilter extends Filter implements \CHAOS\Harvester\Loadable {
	
	/**
	 * Constructs a new embedded filter.
	 * @param \CHAOS\Harvester\ChaosHarvester $harvester a reference to the harvester which is going to ask this filter.
	 * @param string $name The name of the filter, from the configuration.
	 * @param string[string] $parameters An array of key => value parameters, from the configuration. 
	 */
	public function __construct($harvester, $name, $parameters = array()) {
		$this->_harvester = $harvester;
		$this->_harvester->debug("A ".__CLASS__." named '$name' was constructing.");
	}
	
	public function passes($externalObject) {
		$this->_harvester->debug(__CLASS__." is processing.");
		return call_user_func($this->_function, $externalObject);
	}
	
	/**
	 * This is the function to call in order to check if an external object passes.
	 * @var function
	 */
	protected $_function;
	
	/**
	 * Sets the body of the code which is to be run in this embedded PHP filter.
	 * This method initializes the $_function field.
	 * @param string $code
	 */
	public function setCode($code) {
		$this->_function = create_function('$externalObject', $code);
	}
}