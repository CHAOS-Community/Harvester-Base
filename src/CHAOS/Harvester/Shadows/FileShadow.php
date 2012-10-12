<?php
namespace CHAOS\Harvester\Shadows;
class FileShadow extends Shadow {
	
	public $parentFileID;
	public $formatID;
	public $destinationID;
	public $filename;
	public $originalFilename;
	public $folderPath;
	
	public function commit($harvester) {
		$harvester->debug("Committing the shadow of a file.");
	}
}