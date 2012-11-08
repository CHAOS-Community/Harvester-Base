<?php

namespace CHAOS;
use CHAOS\Portal\Client\PortalClient;

class SessionRefreshingPortalClient extends PortalClient {
	
	private $lastSessionRefresh;
	
	/**
	 * How long time is a CHAOS session timeout?
	 * @var string Will be appended a '-' and used as argument for a call to the strtotime function.
	 */
	const SESSION_TIMEOUT = '18 minutes';
	
	public function CallService($path, $method, array $parameters = null, $requiresSession = true) {
		$timeoutTime = strtotime('-'.self::SESSION_TIMEOUT);
		if($this->SessionGUID() !== null && $this->lastSessionRefresh == null) {
			// Initialize ...
			$this->lastSessionRefresh = time();
		} elseif($this->SessionGUID() !== null && $this->lastSessionRefresh < $timeoutTime) {
			printf("Updating chaos session: ");
			// We have to do this to prevent endless recursion.
			$this->lastSessionRefresh = time();
			
			$response = $this->Session()->Update();
			if($response->WasSuccess() && $response->Portal()->WasSuccess()) {
				printf("Success.\n");
			} else {
				printf("Failed!\n");
			}
		}
		timed();
		$response = parent::CallService($path, $method, $parameters, $requiresSession);
		timed('chaos');
		return $response;
	}
}