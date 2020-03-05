<?php

/**
 * @file tests/ServiceDocumentTest.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ServiceDocumentTest
 * @brief Service document tests for Sword gateway plugin
 */

require_once __DIR__ . '/../swordappv2-php-library/swordappclient.php';

class ServiceDocumentTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->sac = new SWORDAPPClient();
        $this->endpoint_url = getenv('SWORD_ENDPOINT_URL');
        if(empty($this->endpoint_url)) {
            throw new Exception('Missing environment variable: SWORD_ENDPOINT_URL');
        }

        $this->doc = $this->sac->servicedocument(
            $this->endpoint_url . '/servicedocument',
            'admin',
            'admin',
            ''
        );
        $this->workspace = $this->doc->sac_workspaces[0];
    }

    public function testWorkspaceTitle() {
        $this->assertEquals($this->workspace->sac_workspacetitle->__toString(), "Journal of Public Knowledge");
    }

    public function testCollectionIRIs() {
        foreach ($this->workspace->sac_collections as $i => $coll) {
            $this->assertEquals(
                $coll->sac_href->__toString(),
                $this->endpoint_url . "/sections/" . (intval($i) + 1)
            );
        }
    }

    //TODO - make sure only GET requests are handled
    public function testNonGetRequest() {
    }
}
