<?php

/**
 * @defgroup oai_format_dc Dublin Core OAI format plugin
 */

/**
 * @file plugins/oaiMetadataFormats/dc/OAIMetadataFormat_DC.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OAIMetadataFormat_DC
 * @ingroup oai_format_dc
 * @see OAI
 *
 * @brief OAI metadata format class -- Dublin Core.
 */

import('plugins.metadata.xmdp22.schema.Xmdp22Schema');

class OAIMetadataFormat_XMDP extends OAIMetadataFormat {

	/**
	 * @copydoc OAIMetadataFormat::toXML
	 */
	function toXml(&$record, $format = null) {
		$publicationFormat =& $record->getData('publicationFormat');
		$description = $publicationFormat->extractMetadata(new Xmdp22Schema());

		$response = "<oai_dc:dc\n" .
			"\txmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\"\n" .
			"\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n" .
			"\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
			"\txsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/\n" .
			"\thttp://www.openarchives.org/OAI/2.0/oai_dc.xsd\">\n";

		foreach($description->getProperties() as $propertyName => $property) { /* @var $property MetadataProperty */
			if ($description->hasStatement($propertyName)) {
				if ($property->getTranslated()) {
					$values = $description->getStatementTranslations($propertyName);
				} else {
					$values = $description->getStatement($propertyName);
				}
				$response .= $this->formatElement($propertyName, $values, $property->getTranslated());
			}
		}

		$response .= "</oai_dc:dc>\n";

		return $response;
	}

	/**
	 * Format XML for single DC element.
	 * @param $propertyName string
	 * @param $value array
	 * @param $multilingual boolean optional
	 */
	function formatElement($propertyName, $values, $multilingual = false) {
		if (!is_array($values)) $values = array($values);

		// Translate the property name to XML syntax.
		$openingElement = str_replace(array('[@', ']'), array(' ',''), $propertyName);
		$closingElement = String::regexp_replace('/\[@.*/', '', $propertyName);

		// Create the actual XML entry.
		$response = '';
		foreach ($values as $key => $value) {
			if ($multilingual) {
				$key = str_replace('_', '-', $key);
				assert(is_array($value));
				foreach ($value as $subValue) {
					if ($key == METADATA_DESCRIPTION_UNKNOWN_LOCALE) {
						$response .= "\t<$openingElement>" . OAIUtils::prepOutput($subValue) . "</$closingElement>\n";
					} else {
						$response .= "\t<$openingElement xml:lang=\"$key\">" . OAIUtils::prepOutput($subValue) . "</$closingElement>\n";
					}
				}
			} else {
				assert(is_scalar($value));
				$response .= "\t<$openingElement>" . OAIUtils::prepOutput($value) . "</$closingElement>\n";
			}
		}
		return $response;
	}
}

?>
