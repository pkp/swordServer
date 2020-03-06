<?php

/**
 * @file tests/DepositTest.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class DepositTest
 * @brief Deposit tests for Sword gateway plugin
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../swordappv2-php-library/swordappclient.php';

class DepositTest extends PHPUnit\Framework\TestCase
{
	public function setUp(): void
	{
		$this->sac = new SWORDAPPClient();
		$this->endpoint_url = getenv('SWORD_ENDPOINT_URL');
		if(empty($this->endpoint_url)) {
			throw new Exception('Missing environment variable: SWORD_ENDPOINT_URL');
		}

		$this->coll_url = $this->endpoint_url . "/sections/1";
	}

	public function testBasicDeposit() {
		$user = 'admin';
		$password = 'admin';
		$path = dirname(__FILE__) . '/mets-and-pdf-deposit.zip';
		$response = $this->sac->deposit(
			$this->coll_url,
			$user,
			$password,
			'',
			$path,
			'http://purl.org/net/sword/package/METSDSpaceSIP',
			'application/zip', false, true
		);
		if(is_a($response, "SWORDAPPErrorDocument")) {
				throw new Exception($response->toString());
		}
		// DepositReceipt
		$this->assertEquals(
			$response->sac_title,
			"Cyclomatic Complexity: theme and variations"
		);

		$edit_link = array_filter($response->sac_links, function($link) { return $link->sac_linkrel == 'edit'; });
		$this->assertCount(1, $edit_link);
		$add_link = array_filter($response->sac_links, function($link) { return $link->sac_linkrel == 'http://purl.org/net/sword/terms/add'; });
		$this->assertCount(1, $add_link);

		$stmt_links = array_filter($response->sac_links, function($link) { return $link->sac_linkrel == 'http://purl.org/net/sword/terms/statement'; });
		$this->assertCount(1, $stmt_links);

		$this->assertEquals(
			$response->sac_treatment,
			"Posted to the Article Submission Queue"
		);
		// SwordStatement
		$stmt_link = array_shift($stmt_links);
		$stmt_href = $stmt_link->sac_linkhref->__toString();
		$stmt = $this->sac->retrieveAtomStatement($stmt_href, $user, $password, '');
		$this->assertEquals(
			$stmt->sac_state_description->__toString(),
			"Deposited to Articles"
		);
	}

	public function testUnauthorizedDeposit() {
		$path = dirname(__FILE__) . '/mets-and-pdf-deposit.zip';
		$response = $this->sac->deposit(
			$this->coll_url,
			'nobody',
			'badpassword',
			'',
			$path,
			'http://purl.org/net/sword/package/METSDSpaceSIP',
			'application/zip', false, true
		);

		$this->assertEquals(
			$response->sac_status,
			401
		);
	}
}
