# OJS SWORD Server Plugin

This plugin will add a SWORD service to OJS. For now, it
only provides a partial implementation of the SWORD ServiceDocument.

## Install and Test

Check this repo out to `plugins/gateway`:

    cd <path_to_ojs_root>/plugins/gateway
    git clone https://github.com/quoideneuf/ojs_sword_server
    cd ojs_sword_server
    git clone https://github.com/swordapp/swordappv2-php-library
    composer update

Enable the plugin under Settings -> Website -> Plugins ->  Gateway Plugins

Start OJS on `localhost:8000` using the test database.

Check out the SWORD client library and run tests:

    git clone https://github.com/swordapp/swordappv2-php-library
    export SWORD_ENDPOINT_URL=http://localhost:8000/index.php/publicknowledge/gateway/plugin/swordServer
    composer test
