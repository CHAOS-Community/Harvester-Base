<?php
namespace CHAOS\Harvester;
interface IExternalClient extends Loadable {
	public function setParameters($parameters);
	public function sanityCheck();
}