<?php

/**
 * @file ServiceDocument.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ServiceDocument
 * @brief Sword gateway plugin
 */

namespace APP\plugins\gateways\swordServer;

class ServiceDocument extends \DOMDocument {

	/**
	 * Constructor
	 */
	function __construct($journal, $sections, $url) {
		parent::__construct();
		$baseUrl = substr($url, 0, strrpos($url, "/"));
		$root = $this->createElementNS('http://www.w3.org/2007/app', 'service');
		$this->appendChild($root);
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dcterms', 'http://purl.org/dc/terms/');
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sword', 'http://purl.org/net/sword/terms/');
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');

		$root->appendChild($this->createElement('sword:version', '2.0'));
		$workspace = $this->createElement('workspace');
		$root->appendChild($workspace);
		$workspace->appendChild($this->createElement('atom:title', $journal->getLocalizedPageHeaderTitle()));
		foreach ($sections as $key => $section) {
			$collection = $this->createElement('collection');
			$href_attr = new \DOMAttr('href', $baseUrl . "/sections/" . $section->_data['id']);
			$collection->appendChild($href_attr);
			$collection->appendChild($this->createElement('atom:title', $section->getLocalizedTitle()));
			$collection->appendChild($this->createElement('accept', '*/*'));
			$accept2 = $this->createElement('accept', '*/*');
			$accept2_attr = new \DOMAttr('alternate', 'multipart-related');
			$accept2->appendChild($accept2_attr);
			$collection->appendChild($accept2);
			$workspace->appendChild($collection);
		}
	}
}
