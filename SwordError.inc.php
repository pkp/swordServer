<?php

/**
 * @file plugins/gateways/swordserver/DepositReceipt.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SwordError
 * @ingroup plugins_gateways_swordserver
 *
 * @brief Sword gateway plugin
 */

class SwordError extends DOMDocument {

	function __construct($data) {
		parent::__construct();
		$root = $this->createElementNS('http://purl.org/net/sword', 'sword:error') ;
		$this->appendChild($root);
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
		$root->appendChild($this->createElement('atom:summary', $data['summary']));
	}
}
