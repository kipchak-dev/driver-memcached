<?php

namespace Mamluk\Kipchak\Driver\Memcached;

use Mamluk\Kipchak\Interfaces;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Mamluk\Kipchak\Driver\Config\Driver as Config;
use Memcached as MC;

class Driver implements Interfaces\Driver
{
    protected static ContainerInterface $container;

    public static function initialise(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function set(): void
    {
        if (!empty(Config::get('kipchak.memcached'))) {
            $memcachedConfig = Config::get('kipchak.memcached');
            if ($memcachedConfig['enabled'] && isset($memcachedConfig['pools'])) {
                foreach ($memcachedConfig['pools'] as $poolName => $poolConnection) {
                    self::$container->set('drivers.cache.memcached.' . $poolName, function (ContainerInterface $c) use ($memcachedConfig, $poolConnection, $poolName): MemcachedAdapter {
                        $namespace = isset(Config::get('kipchak.api')['name']) ?? 'apiCache';
                        $memcached = new MC($namespace);
                        // Reset the list of pools in the server first before adding them
                        $memcached->resetServerList();
                        if (isset($memcachedConfig['pools_options'][$poolName])) {
                            foreach ($memcachedConfig['pools_options'][$poolName] as $mcOption => $mcVal) {
                                $memcached->setOption($mcOption, $mcVal);
                            }
                        }
                        $memcached->addServers($poolConnection);

                        return new MemcachedAdapter($memcached, $namespace, 3600);
                    });
                }
            }
        }
    }

    public static function get(?string $configKey = null): MemcachedAdapter
    {
        return self::$container->get('drivers.cache.memcached.' . $configKey ?? 'default');
    }

}