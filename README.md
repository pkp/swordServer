# OJS SWORD Server Plugin

This plugin will add a SWORD service to OJS.

## Install
Use the plugin gallery from within OJS to install the plugin.

## Use

This plugin provides services at the following URLs:

Service document: .../index.php/[journal path]/gateway/plugin/swordServer/servicedocument
Section deposit point: .../index.php/publicknowledge/gateway/plugin/swordServer/sections/2

## Tests

Enable the plugin under Settings -> Website -> Plugins ->  Gateway Plugins

Start OJS on `localhost:8000` using the test database.

Check out the SWORD client library and run tests:

    export SWORD_ENDPOINT_URL=http://localhost:8000/index.php/publicknowledge/gateway/plugin/swordServer
    composer test
