# Plugin Installer for Roundcube

This installer ensures that plugins end up in the correct directory:

 * `<roundcube-root>/plugins/plugin-name`

## Minimum setup

 * create a `composer.json` file in your plugin's repository
 * add the following contents

### sample composer.json for plugins

    {
        "name": "yourvendor/plugin-name",
        "license": "the license",
        "description": "tell the world what your plugin is good at",
        "type": "roundcube-plugin",
        "authors": [
            {
                "name": "<your-name>",
                "email": "<your-email>"
            }
        ],
        "repositories": [
            {
                "type": "composer",
                "url": "http://plugins.roundcube.net"
            }
        ]
        "require": {
            "roundcube/plugin-installer": "*"
        },
        "minimum-stability": "dev-master"
    }

  * Submit your plugin to [plugins.roundcube.net](http://plugins.roundcube.net).

## Installation

 * clone Roundcube
 * `cp composer.json-dist composer.json`
 * add your plugin in the `require` section of composer.json
 * `composer.phar install`

Read the whole story at [plugins.roundcube.net](http://plugins.roundcube.net/about).
