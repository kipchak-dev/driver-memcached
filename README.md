# CouchDB Driver for Kipchak

This driver is based on the `symfony/cache` from the Symfony Framework.

It is used to interact with CouchDB databases.

#### Composer Package 

`kipchak/driver-memcached`

#### Driver Name

`cache.memcached.__pool_name__`

#### Sample Config File:

This config file should be placed in your Kipchak project's config directory, as in the starter 
project at https://1x.ax/mamluk/kipchak/starter/~files/master/config and **must be named `kipchak.memcached.php`**.

```
<?php

use function Mamluk\Kipchak\env;

return [
    'enabled' => true,
    'pools' => [
        'cache1' => [
            [
                'host' => env('MEMCACHED_HOST', 'memcached'),
                'port' => env('MEMCACHED_PORT', 11211),
            ]
        ],
        'cache2' => [
            [
                'host' => env('MEMCACHED_HOST', 'memcached'),
                'port' => env('MEMCACHED_PORT', 11211),
            ]
        ]
    ],
    // Please see https://www.php.net/manual/en/memcached.constants.php for more details.
    // You can specify options for the Memcached PECL below and Kipchak will apply them as it bootstraps to the appropriate pool.
    'pools_options' => [
        'cache1' => [
            Memcached::OPT_PREFIX_KEY => '',
            Memcached::OPT_CONNECT_TIMEOUT => 1000, // millisconeds
            Memcached::OPT_RETRY_TIMEOUT => 1, // seconds
            Memcached::OPT_POLL_TIMEOUT => 1000, // milliseconds
            Memcached::OPT_SERVER_FAILURE_LIMIT => 3,
            Memcached::OPT_REMOVE_FAILED_SERVERS => true,
            Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT
        ],
        'cache2' => [
            Memcached::OPT_PREFIX_KEY => '',
            Memcached::OPT_CONNECT_TIMEOUT => 1000, // millisconeds
            Memcached::OPT_RETRY_TIMEOUT => 1, // seconds
            Memcached::OPT_POLL_TIMEOUT => 1000, // milliseconds
            Memcached::OPT_SERVER_FAILURE_LIMIT => 3,
            Memcached::OPT_REMOVE_FAILED_SERVERS => true,
            Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT
        ],
    ]
];

```

## How to use it?

Install it via composer: `composer require kipchak/driver-memcached`.

The dependency name of this driver is `cache.memcached.__pool_name__`.

### Example Usage

```
$mc = $this->container->get('cache.memcached.cache1');
// Use the Memcached ($mc) client as you normally would.
```
See the methods available in the client here: https://symfony.com/doc/current/components/cache.html

## What is a Kipchak Driver?

Kipchak Drivers are used to connect Kipchak to various data sources or storage systems. 

They provide a standardized interface for interacting with different data sources, allowing developers to 
focus on building their applications rather than dealing with the complexities of each data source.

Drivers were introduced as a part of the Kipchak 2.0 release.

Drivers are basically Slim Dependencies injected into Kipchak's Service Container.

## How do Kipchak Drivers work?

Kipchak drivers are wired into the Service Container via a config file.

Each driver has a pre-defined dependency name which may be sufficed with a config property, for instance, in the case 
of multiple database connections or S3 buckets.

So you may access the driver by invoking `$this->container->get('database.couchdb.default'), where 
`database.couchdb.` is the name of the driver and the `default` is the name of the connection specifiedin the config file.

