<?php

abstract class AChaosFileExtractor {
	const PROGRESS_END_CHAR = '|';
	
	/**
	 * Process the DFI movieitem.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient The Chaos client to use for the importing.
	 * @param Object $externalClient The remote client to use for importing.
	 * @param Object $externalObject The DFI movie item.
	 * @param stdClass $object Representing the DKA program in the Chaos service, of which the images should be added to.
	 * @return array An array of processed files.
	 */
	public abstract function process($harvester, $object, $externalObject, &$extras);
	
	/**
	 * Gets or creates a new file reference.
	 * If the file is already present on the system it simply returns the file.
	 * NB: This is not correctly implemented yet, it will simply create a new file no matter what.
	 * This is due to the state of the Chaos PHP clients implementation state.
	 * @param CHAOS\Portal\Client\PortalClient $chaosClient The client to use for the importation.
	 * @param stdClass $objectGUID
	 * @param int|null $parentFileID The FileID of an original file this file was created from, otherwise null.
	 * @param int $formatID
	 * @param int $destinationID
	 * @param string $filename
	 * @param string $originalFilename
	 * @param string $folderPath
	 * @return \CHAOS\Portal\Client\Data\ServiceResult
	 */
	protected function getOrCreateFile($harvester, $object, $parentFileID, $formatID, $destinationID, $filename, $originalFilename, $folderPath, $printProgress = true) {
		if($object == null) {
			throw new Exception("Cannot get or create a file from a null object.");
		}
		$formatID = (int) $formatID;
		// Check if it is on the $object's list of Files.
		
		// Get is not implemented, so we cannot lookup the file.
		// But we can iterate over the objects files.
		foreach($object->Files as $f) {
			// Consider to check on the $f->URL instead ...
			$fileEquals =
				$f->ParentID === $parentFileID &&
				$f->FormatID === $formatID &&
				$f->Filename === $filename &&
				$f->OriginalFilename === $originalFilename &&
				strstr($f->URL, $folderPath); // This is because the $folderPath cannot be extracted directly from the Chaos File record.
			if($fileEquals) {
				// A file has already been created.
				if($printProgress) {
					echo ".";
				}
				return $f;
			}
		}
		
		// File is not known to the Chaos system, creating it.
		$response = $harvester->getChaosClient()->File()->Create($object->GUID, $parentFileID, $formatID, $destinationID, $filename, $originalFilename, $folderPath);
		if(!$response->WasSuccess()) {
			if($printProgress) {
				echo "!";
			}
			throw new RuntimeException("Failed to create the file in the Chaos service: ". $response->Error()->Message());
		} elseif (!$response->MCM()->WasSuccess()) {
			if($printProgress) {
				echo "!";
			}
			throw new RuntimeException("Failed to create the file in the Chaos service: (MCM error): ". $response->MCM()->Error()->Message());
		} else {
			if($printProgress) {
				echo "+";
			}
			$results = $response->MCM()->Results();
			return $results[0];
		}
	}
} 