<?php
namespace CHAOS\Harvester\Filters;
class EmbeddedFilter extends Filter {
	
	public function passes($externalObject, $objectShadow) {
		if($this->_name) {
			$this->_harvester->debug("The processor is filtered by the '%s' embedded filter.", $this->_name);
		} else {
			$this->_harvester->debug("The processor is filtered by an unnamed embedded filter.");
		}
		
		return call_user_func_array($this->_function, array($externalObject, $objectShadow));
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
		$this->_function = create_function('$externalObject,$objectShadow', $code);
	}
}