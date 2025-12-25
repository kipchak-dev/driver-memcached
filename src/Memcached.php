<?php

namespace Kipchak\Driver\Memcached;

use Kipchak\Core\Interfaces;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Kipchak\Driver\Config\Config;
use Memcached as MC;

class Memcached implements Interfaces\Driver
{
    protected static ?ContainerInterface $container = null;


    public static function initialise(ContainerInterface $container, ?string $configFileName = null): void
    {
        self::$container = $container;
        self::set($configFileName);
    }

    public static function set(?string $configFileName = null): void
    {
        $configFileName = $configFileName ?? 'kipchak.memcached';
        if (!empty(Config::get($configFileName))) {
            $memcachedConfig = Config::get($configFileName);
            if ($memcachedConfig['enabled'] && isset($memcachedConfig['pools'])) {
                foreach ($memcachedConfig['pools'] as $poolName => $poolConnection) {
                    self::$container->set('drivers.cache.memcached.' . $poolName, function (ContainerInterface $c) use ($memcachedConfig, $poolConnection, $poolName): MemcachedAdapter {
                        return self::createAdapter($memcachedConfig, $poolConnection, $poolName);
                    });
                }
            }
        }
    }

    public static function get(?string $configKey = null): MemcachedAdapter
    {
        return self::$container->get('drivers.cache.memcached.' . $configKey ?? 'cache');
    }

    protected static function createAdapter($memcachedConfig, $poolConnection, $poolName): MemcachedAdapter
    {
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
    }
}