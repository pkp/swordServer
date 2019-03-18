<?php

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
            'whoever',
            'doesntmatter',
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
