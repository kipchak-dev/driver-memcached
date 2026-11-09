# Memcached Driver for Kipchak

This is a memcached caching driver for Kipchak. It's based on the Symfony Cache Component `symfony/cache`.

It supports multiple pools of Memcached servers.

#### Composer Package 

`kipchak/driver-memcached`

#### Sample Config File:

This config file should be placed in your Kipchak project's config directory, as in the examples
project at https://github.com/kipchak-dev/examples/blob/master/config/kipchak.memcached.php.

The config file should look like this (also avalable in sample.config.php):

```
<?php

use function Mamluk\Kipchak\env;

return [
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

### Example Usage

```php
// Load the HTTP driver.
$cache = \Kipchak\Driver\Memcached\Memcached::get('cache'); // Cache is a pool name defined in the config file.
// Use the $cache client as you would the Symfony Cache Component.
```

### Raw connection

PSR-6 cannot express atomic operations such as `add`, `increment` or `cas`. For those, `connection()` returns the
pool's underlying `\Memcached` client, the same one its cache adapter uses.

```php
$memcached = \Kipchak\Driver\Memcached\Memcached::connection('cache');
$firstSeen = $memcached->add('myapi.nonce.abc', 1, 300); // false if the key already exists
```

Keys written through the raw connection are not namespaced by the adapter, so prefix your own keys.

## What is a Kipchak Driver?

Kipchak Drivers are used to connect Kipchak to various data sources or storage systems.

They provide a standardized interface for interacting with different data sources, allowing developers to
focus on building their applications rather than dealing with the complexities of each data source.

Drivers were introduced as a part of the Kipchak 2.0 release.

Drivers are basically Container Dependencies injected into Kipchak's Service Container.

## How do Kipchak Drivers work?

Some Kipchak drivers are wired into the Service Container via a config file (where required).

If applicable, you will find a sample config file in this repository as well as in the starter project at https://1x.ax/mamluk/kipchak/starter.

Each driver defines an implementation of Kipchak's Driver Interface. Where applicable, the dependency may
accept a parameter for a specific instance of the driver specified as a config property, for instance, in the case
of multiple database connections or S3 buckets.

So you may access the driver by invoking \Mamluk\Kipchak\Driver\Memcached\Memcached::get('cache'), where
`cache` is the name of one of the memcached connection pools specified in the config file.