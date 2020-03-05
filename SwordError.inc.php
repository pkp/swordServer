<?php

/**
 * @file plugins/gateways/swordserver/DepositReceipt.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordError
 * @ingroup plugins_gateways_swordserver
 *
 * @brief Sword gateway plugin
 */

class SwordError extends DOMDocument {

	/**
	 * Constructor.
	 * @param $data array
	 */
	function __construct($data) {
		parent::__construct();
		$root = $this->createElementNS('http://purl.org/net/sword', 'sword:error') ;
		$this->appendChild($root);
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
		$root->appendChild($this->createElement('atom:summary', $data['summary']));
	}
}
