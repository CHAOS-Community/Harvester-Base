<?php
namespace CHAOS\Harvester;
interface IExternalClient extends Loadable {
	public function sanityCheck();
}