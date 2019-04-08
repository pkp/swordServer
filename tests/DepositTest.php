<?php

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
				$path = dirname(__FILE__) . '/mets-and-pdf-deposit.zip';
				$response = $this->sac->deposit(
						$this->coll_url,
						'anyone',
						'whatever',
						'',
						$path,
						'http://purl.org/net/sword/package/METSDSpaceSIP',
						'application/zip', false, true
				);

				$this->assertEquals(
						$response->sac_title,
						"Cyclomatic Complexity: theme and variations"
				);

				$edit_link = array_filter($response->sac_links, function($link) { return $link->sac_linkrel == 'edit'; });
				$this->assertCount(1, $edit_link);
				$add_link = array_filter($response->sac_links, function($link) { return $link->sac_linkrel == 'http://purl.org/net/sword/terms/add'; });
				$this->assertCount(1, $add_link);

				$this->assertEquals(
						$response->sac_treatment,
						"Posted to the Article Submission Queue"
				);
		}
}
