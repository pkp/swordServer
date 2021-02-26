# OJS SWORD Server Plugin

This plugin will add a SWORD service to OJS. For now, it
only provides a partial implementation of the SWORD ServiceDocument.

## Install and Test
Use the plugin gallery from within OJS to install the plugin.

Enable the plugin under Settings -> Website -> Plugins ->  Gateway Plugins

Start OJS on `localhost:8000` using the test database.

Check out the SWORD client library and run tests:

    export SWORD_ENDPOINT_URL=http://localhost:8000/index.php/publicknowledge/gateway/plugin/swordServer
    composer test
