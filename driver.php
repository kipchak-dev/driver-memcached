<?php

use Psr\Container\ContainerInterface;
use Mamluk\Kipchak\Driver\Memcached\Driver;

/**
 * @var $container ContainerInterface
 */

Driver::initialise($container);
Driver::set();