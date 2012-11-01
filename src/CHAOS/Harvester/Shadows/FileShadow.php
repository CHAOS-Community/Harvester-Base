<?php
namespace CHAOS\Harvester\Shadows;
class FileShadow extends Shadow {
	
	public $formatID;
	public $destinationID;
	public $filename;
	public $originalFilename;
	public $folderPath;
	
	/**
	 * 
	 * @var \CHAOS\Harvester\Shadows\FileShadow
	 */
	public $parentFileShadow;
	
	/**
	 * If only the parent file id is known.
	 * @var int
	 */
	public $parentFileID;
	
	protected $file;
	
	public function getFile() {
		return $file;
	}
	
	public function commit($harvester, $parent = null) {
		if($this->file != null) {
			$harvester->debug("Asked to commit a shadow of a file that has already been committed.");
			return $this->file;
		}
		
		$harvester->debug("Committing the shadow of a file.");
		
		if($parent == null || !$parent instanceof ObjectShadow) {
			trigger_error('The shadow given as $parent argument has to be initialized and of type Object Shadow');
		}
		$object = $parent->get($harvester);
		
		$file = array_filter($object->Files, array($this, 'matchFile'));
		if(count($file) == 1) {
			$file = array_pop($file);
		} elseif(count($file) > 1) {
			$file = array_pop($file);
			trigger_error('It looks like the chaos object has more than one of the same file.', E_USER_WARNING);
		} else {
			$file = null;
		}
		
		if($file != null) {
			// File already exists in the service.
			$harvester->debug('Reusing file '.$file->ID);
			$this->file = $file;
			$this->file->status = 'reused';
		} else {
			if($this->parentFileShadow != null && $this->parentFileID == null) {
				// Commit any parent files before this.
				$this->parentFileID = $this->parentFileShadow->commit($harvester)->ID;
			}
			if($this->parentFileID != null) {
				$harvester->debug('This file is a child of the file with ID = '.$this->parentFileID);
			}
			
			$response = $harvester->getChaosClient()->File()->Create($object->GUID, $this->parentFileID, $this->formatID, $this->destinationID, $this->filename, $this->originalFilename, $this->folderPath);
			if(!$response->WasSuccess()) {
				throw new RuntimeException('General error setting metadata for schema GUID = '.$this->metadataSchemaGUID . ': '.$response->Error()->Message());
			}
			if(!$response->MCM()->WasSuccess()) {
				throw new RuntimeException('MCM error setting metadata for schema GUID = '.$this->metadataSchemaGUID . ': '.$response->MCM()->Error()->Message());
			}
			$results = $response->MCM()->Results();
			$this->file = array_pop($results);
			$this->file->status = 'created';
		}
		return $this->file;
	}
	
	protected function matchFile($file) {
		if($this->originalFilename != $file->OriginalFilename) {
			return false;
		} elseif($this->formatID != $file->FormatID) {
			return false;
		} elseif(strstr($file->URL, $this->folderPath) === false) {
			// This actually depends on how the destination is specified in the chaos service.
			return false;
		} else {
			return true;
		}
	}
	
	
	/**
	 * Generates a function that can be used in an array_filter to select just the files
	 * with same original filename.
	 * @param FileShadow $f
	 */
	public static function hasSameOriginalFilenameAndFolderPath($f) {
		return function($file) use($f) {
			return $f->originalFilename == $file->originalFilename && $f->folderPath == $file->folderPath;
		};
	}
}