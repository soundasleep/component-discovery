component-discovery
===================

_component-discovery_ is a Composer-enabled PHP script to define _types_ of
PHP components which can then be loaded automatically, generated from your
resolved dependencies.

Instances of components are defined with a key/class instance mapping.
For ease of use, components loaded through Composer should define their own
autoload mapping in their `composer.json`; however you can still define
your own `spl_autoload_register` function.

## Configuring

First include `component-discovery` as a requirement in your project `composer.json`,
and run `composer update` to install it into your project:

```json
{
  "require": {
    "soundasleep/component-discovery": "dev-master"
  }
}
```

Now create a `discovery.json` in your project, to define the types of components to discover:

```json
{
  "components": {
    "currencies": "currencies.json",
    "jobs": "jobs.json"
  },
  "src": "vendor/*/*",
  "dest": "inc/components"
}
```

_component-discovery_ will look in all the `src` folders for the definition files
(e.g. `currencies.json`) to find keys and class mappings. For example, in your
`vendor/my/package/currencies.json`:

```json
{
  "btc": "\\Currency\\Bitcoin",
  "ltc": "\\Currency\\Litecoin"
}
```

Note that you need to escape out the namespace characters in the class names.
You can now call _component-discovery_ in your build script or manually.

You can also define your component file as an array, rather than a dictionary, and
values of this array will also be used as the keys for each value.

```json
[
  "\\Currency\\Bitcoin",
  "\\Currency\\Litecoin"
]
```

## Building

Run the generate script, either with your build script or manually, with
a given root directory:

```
php -f vendor/soundasleep/component-discovery/generate.php .
```

This will generate various files under the `src` directory, that provide
all of the runtime mappings between discovery types, keys and class instances.

For example, this can generate the following include `inc/components/Currencies.php`,
based on the components you have loaded with Composer in `vendor/`,
listing all of the _Currency_ components:

```php
<?php

/**
 * @generated by component-discovery DO NOT EDIT
 */

namespace DiscoveredComponents;

class Currencies extends \ComponentDiscovery\Base {
  function getKeys() {
    return ["btc", "ltc", "nmc"];
  }

  function getInstance($key, $config = false) {
    switch ($key) {
      case "btc": return new \Currency\Bitcoin($config);
      case "ltc": return new \Currency\Litecoin($config);
      case "nmc": return new \Currency\Namecoin($config);
      default:
        throw new \ComponentDiscovery\DiscoveryException("Could not find any Currencies with key '\$key'");
    }
  }

  function getAllInstances($config = false) {
    return ["btc" => new \Currency\Bitcoin($config), "ltc" => new \Currency\Litecoin($config), "nmc" => new \Currency\Namecoin($config)];
  }
}
?>
```

## Using

For example, listing all loaded currencies from the example before:

```php
require("inc/components/Currencies.php");
$discovery = new DiscoveredComponents/Currencies();
print_r($discovery->getKeys());
```

Loading a currency based on a key:

```php
require("inc/components/Currencies.php");
$discovery = new DiscoveredComponents/Currencies();
$currency = $discovery->getInstance("btc");
echo "btc = " . $currency->getName();
```

## Creating more complex mappings

You can also create more complex definitions for each type of component to discover:

```json
{
  "components": {
    "currencies": {
      "file": "currencies.json",
      "instanceof": "\\Openclerk\\Currencies\\Currency",
      "maps": {
        "getKeyForAbbr": "getAbbr"
      },
      "masks": {
        "getCryptocurrencies": "isCryptocurrency",
        "getFiatCurrencies": "isFiat",
        "getCommodityCurrencies": "isCommodity"
      },
      "lists": {
        "getAbbrs": "getAbbr"
      },
      "instances": {
        "getBalanceCurrencies": "\\Openclerk\\Currencies\\BalanceableCurrency"
      }
    }
  }
}
```

* `instanceof`: checks that each class found in each component is an instance of the given class or interface
* `maps`: creates functions which return a key based on the return value of this method on each class
* `masks`: creates functions which return a list of all keys which classes return `true` with this method
* `lists`: creates functions which return a list of return values of this method on each class
* `instances`: creates functions which return a list of all keys which classes are instances of the given class or interface

For example:

```php
  static function getKeyForAbbr($input) {
    switch ($input) {
      case "BTC": return "btc";
      default:
        throw new \ComponentDiscovery\DiscoveryException("Could not find any matching getKeyForAbbr for '$input'");
    }
  }

  static function getCryptocurrencies() {
    return array("btc");
  }

  static function getFiatCurrencies() {
    return array();
  }

  static function getCommodityCurrencies() {
    return array();
  }

  static function getAbbrs() {
    return array("btc" => "BTC");
  }

  static function getBalanceCurrencies() {
    return array("btc");
  }
```

## TODOs

1. More documentation, especially default `discovery.json` parameters
3. Example projects using _component-discovery_
4. Create `grunt` task `grunt-php-component-discovery` to wrap the manual PHP command
5. Release 0.1 version
