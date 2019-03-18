<?php

require_once __DIR__ . '/../swordappv2-php-library/swordappclient.php';

class DepositTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->sac = new SWORDAPPClient();
        $this->endpoint_url = getenv('SWORD_ENDPOINT_URL');
        if(empty($this->endpoint_url)) {
            throw new Exception('Missing environment variable: SWORD_ENDPOINT_URL');
        }

        $this->coll_url = $this->endpoint_url . "/sections/1";
    }

    public function testBasicDeposit() {
        $tmp = tmpfile();
        fwrite($tmp, "ojs sword deposit file contents");
        $path = stream_get_meta_data($tmp)['uri'];

        $response = $this->sac->deposit(
            $this->coll_url,
            'anyone',
            'whatever',
            '',
            $path
        );

        $this->assertTrue(
            !empty($response->sac_title)
        );
    }
}
