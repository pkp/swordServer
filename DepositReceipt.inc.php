<?php

/**
 * @file plugins/gateways/swordserver/DepositReceipt.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DepositReceipt
 * @ingroup plugins_gateways_swordserver
 *
 * @brief Sword gateway plugin
 */

class DepositReceipt extends DOMDocument {

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
	}
}
