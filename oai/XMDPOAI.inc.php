<?php

/**
 * @file plugins/oaiMetadataFormats/xmdp/oai/XMDPOAI.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMDPOAI
 * @ingroup oai_omp_xmdp
 * @see PressOAI
 *
 * @brief Additional OAI functions for adding publication format sets.
 */

import('classes.oai.omp.PressOAI');

class XMDPOAI extends PressOAI {
	
	function XMDPOAI() {
		
	}
	
	/**
	 * Add publication format sets
	 */
	function sets($hookName, $params) {
		$pressOAI =& $params[0];
		$offset = $params[1];
		$limit = $params[2];
		$total = $params[3];
		$sets =& $params[4];
	
		$pressDAO =& DAORegistry::getDAO('PressDAO');
		$oaidao = DAORegistry::getDAO('OAIDAO');
		$publicationFormatDAO =& DAORegistry::getDAO('PublicationFormatDAO');
	
		$pressId = $pressOAI->pressId;
		if (isset($pressId)) {
			$presses = array($pressDAO->getById($pressId));
		} else {
			$presses =& $pressDAO->getAll();
			$presses =& $presses->toArray();
		}
	
		$sets = array();
		foreach ($presses as $press) {
			$pressId = $press->getId();
			
			// Default press and series sets
			$sets = array_merge($sets, $oaidao->getSets($pressId, $offset, $limit, $total));
			
			// Additional publication format sets
			$pubFormats =& $publicationFormatDAO->getByContextId($pressId);
			$pubFormatNames = array();
			foreach ($pubFormats->toArray() as $pubFormat) {
				if ( $pubFormat->getIsAvailable() ) {
					$name = strtolower($pubFormat->getLocalizedName());
					$pubFormatNames[$name] = "Publication Format " . strtoupper($name);
				}
			}
		
			foreach ( $pubFormatNames as $abbrev => $name ) {
				array_push($sets, new OAISet($press->getLocalizedAcronym() . ':pubf_' . urlencode($abbrev), $name, ''));
			}
		}
	
 		if ($offset != 0) {
			$sets = array_slice($sets, $offset);
		}
	
		return true;
	}
	
	/**
	 *  Handle requests for records if a publication format set is fiven
	 */
	function records($hookName, $params) {
 		$pressOAI =& $params[0];
		$from = $params[1];
		$until = $params[2];
		$set = $params[3];
		$offset = $params[4];
		$limit = $params[5];
		$total = $params[6];
		$records =& $params[7];
		
		$oaidao = DAORegistry::getDAO('OAIDAO');
		$pressDAO =& DAORegistry::getDAO('PressDAO');
		
		$pressId = $pressOAI->pressId;
		$press = $pressDAO->getById($pressId);
		
		$records = array();
		// Handle publication format sets
		if ( isset($set) && strpos($set, ':pubf_') != False ) {
			// Identify publication format and press
			// TODO: Handle errors, if there is no press with the given acronym
			// TODO: Simply derive press from pressID? Do we need to add the press to the publication format? Check what happens, if we have more than one press ...
			$set_elements = explode(':pubf_', $set);
			if ( sizeof($set_elements) == 2 ) {
				list($pressAcronym, $pubFormatString) = $set_elements;
			}
			$pressRows = $pressDAO->getBySetting("acronym", $pressAcronym);
			if ( $pressRows->getCount() == 1 ) {
				$press = $pressRows->next();
			} else {
				// No way to handle multiple presses with the same acronym (pretty sure OMP does not allow this to happen)
				assert(false);
			}
			
			// Keep only records with the requested publication format
			// FIXME: This might be slow for a large number of records -- handle on database level?
			$allPressRecords = $oaidao->getRecords(array($pressOAI->pressId, null), $from, $until, $set, $offset, $limit, $total);
			foreach ( $allPressRecords as $record ) {
				$pubFormat =& $record->getData('publicationFormat');
				if ( !is_null($pubFormat) && strtolower($pubFormat->getLocalizedName())== $pubFormatString ) {
					array_push($records, $record);
				}
			}
			return True;
		}
		return False;
	}

	function identifiers($hookName, $params) {
 		$pressOAI =& $params[0];
		$from = $params[1];
		$until = $params[2];
		$set = $params[3];
		$offset = $params[4];
		$limit = $params[5];
		$total = $params[6];
		$records =& $params[7];
		
		$oaidao = DAORegistry::getDAO('OAIDAO');
		$pressDAO =& DAORegistry::getDAO('PressDAO');
		$publicationFormatDAO =& DAORegistry::getDAO('PublicationFormatDAO');
		
		$pressId = $pressOAI->pressId;
		$press = $pressDAO->getById($pressId);
		
		$records = array();
		// Handle publication format sets
		if ( isset($set) && strpos($set, ':pubf_') != False ) {
			// Identify publication format and press
			// TODO: Handle errors, if there is no press with the given acronym
			$set_elements = explode(':pubf_', $set);
			if ( sizeof($set_elements) == 2 ) {
				list($pressAcronym, $pubFormatString) = $set_elements;
			}
			$pressRows = $pressDAO->getBySetting("acronym", $pressAcronym);
			if ( $pressRows->getCount() == 1 ) {
				$press = $pressRows->next();
			} else {
				// No way to handle multiple presses with the same acronym (pretty sure OMP does not allow this to happen)
				assert(false);
			}
				
			// Keep only records with the requested publication format
			// FIXME: This might be slow for a large number of records -- handle on database level?
			$allPressRecords = $oaidao->getIdentifiers(array($pressOAI->pressId, null), $from, $until, $set, $offset, $limit, $total);
			foreach ( $allPressRecords as $record ) {
				$pubFormatId = explode("publicationFormat/", $record->identifier)[1];
				$pubFormat = $publicationFormatDAO->getById($pubFormatId);
				if ( !is_null($pubFormat) && strtolower($pubFormat->getLocalizedName())== $pubFormatString ) {
					array_push($records, $record);
				}
			}
			return True;
		}	
		return False;
	}
	
	/**
	 * Add publication format sets to record
	 */	
	function _addPublicationFormatSets($hookName, $params) {
		$record =& $params[0];
		$row = $params[1];		
		error_log('XMDPOAI: row = ' . var_export($row, true));
		$publicationFormatDAO =& DAORegistry::getDAO('PublicationFormatDAO');
		if( isset($row['tombstone_id']) ) {
			$pressAcronym = explode(":", $row['set_spec'])[0];
			$pubFormat = $publicationFormatDAO->getById($row['data_object_id']);
		}
		else if( is_a($record, 'OAIRecord')) {
			$pubFormat = $record->getData('publicationFormat');
			$press = $record->getData('press');
			$pressAcronym = $press->getPath();
		}
		else {
			$pressDAO =& DAORegistry::getDAO('PressDAO');
			$press = $pressDAO->getById($row['press_id']);
			$pressAcronym = $press->getPath();
			$pubFormat = $publicationFormatDAO->getById($row['data_object_id']);
		}		
		if ( isset($pubFormat) ) {
			$record->sets[] =  $pressAcronym . ':pubf_' . strtolower($pubFormat->getLocalizedName());
		}
		return False;
	}
}

?>
