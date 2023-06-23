<?php

/**
 * @file DepositReceipt.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class DepositReceipt
 * @brief Sword gateway plugin
 */

namespace APP\plugins\gateways\swordServer;

class DepositReceipt extends \DOMDocument {

	/**
	 * Constructor.
	 * @param $data array
	 */
	function __construct($data) {
		parent::__construct();
		$root = $this->createElementNS('http://www.w3.org/2005/Atom', 'entry');
		$this->appendChild($root);
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dcterms', 'http://purl.org/dc/terms/');
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sword', 'http://purl.org/net/sword/terms/');
		$root->appendChild($this->createElement('title', $data['title']));

		$editIri = $this->createElement('link');
		$root->appendChild($editIri);
		$editIri->setAttribute('rel', "edit");
		$editIri->setAttribute('href', $data['edit-iri']);

		$swordEditIri = $this->createElement('link');
		$root->appendChild($swordEditIri);
		$swordEditIri->setAttribute('rel', "http://purl.org/net/sword/terms/add");
		$swordEditIri->setAttribute('href', $data['edit-iri']);

		if (isset($data['alternateLink'])) {
			$alternateLinkElement = $this->createElement('link');
			$alternateLinkElement->setAttribute('rel', 'alternate');
			$alternateLinkElement->setAttribute('href', $data['alternateLink']);
			$root->appendChild($alternateLinkElement);
		}

		$stmtIri = $this->createElement('link');
		$root->appendChild($stmtIri);
		$stmtIri->setAttribute('rel', "http://purl.org/net/sword/terms/statement");
		$stmtIri->setAttribute('href', $data['stmt-iri']);

		$swordTreatment = $this->createElement('sword:treatment', 'Posted to the Article Submission Queue');
		$root->appendChild($swordTreatment);
	}
}
