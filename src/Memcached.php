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
            if (isset($memcachedConfig['pools'])) {
                foreach ($memcachedConfig['pools'] as $poolName => $poolConnection) {
                    // The adapter and the raw connection share one client per pool.
                    self::$container->set('drivers.cache.memcached.' . $poolName . '.connection', function (ContainerInterface $c) use ($memcachedConfig, $poolConnection, $poolName): MC {
                        return self::createConnection($memcachedConfig, $poolConnection, $poolName);
                    });
                    self::$container->set('drivers.cache.memcached.' . $poolName, function (ContainerInterface $c) use ($poolName): MemcachedAdapter {
                        return self::createAdapter($c->get('drivers.cache.memcached.' . $poolName . '.connection'));
                    });
                }
            }
        }
    }

    public static function get(?string $configKey = null): MemcachedAdapter
    {
        return self::$container->get('drivers.cache.memcached.' . ($configKey ?? 'cache'));
    }

    /**
     * Returns the underlying \Memcached client for a pool, for operations the PSR-6 adapter cannot express
     * atomically (add, increment, cas). Keys written here are not namespaced by the adapter, so callers should
     * prefix their own keys.
     */
    public static function connection(?string $configKey = null): MC
    {
        return self::$container->get('drivers.cache.memcached.' . ($configKey ?? 'cache') . '.connection');
    }

    protected static function createAdapter(MC $memcached): MemcachedAdapter
    {
        return new MemcachedAdapter($memcached, self::namespace(), 3600);
    }

    protected static function namespace(): string
    {
        return static::namespaceFor(Config::get('kipchak.api'));
    }

    /**
     * The API's name, reduced to the characters Symfony allows in a cache namespace.
     */
    protected static function namespaceFor(array $apiConfig): string
    {
        $name = $apiConfig['name'] ?? '';
        $name = is_string($name) ? preg_replace('/[^-+_.A-Za-z0-9]/', '_', $name) : '';

        return $name === '' ? 'apiCache' : $name;
    }

    protected static function createConnection($memcachedConfig, $poolConnection, $poolName): MC
    {
        // One persistent ID per pool: a shared ID hands every pool the same \Memcached instance, and each
        // pool's resetServerList() below would then wipe the servers of the pools built before it.
        $memcached = new MC(self::namespace() . '.' . $poolName);
        // Reset the list of pools in the server first before adding them
        $memcached->resetServerList();
        if (isset($memcachedConfig['pools_options'][$poolName])) {
            foreach ($memcachedConfig['pools_options'][$poolName] as $mcOption => $mcVal) {
                $memcached->setOption($mcOption, $mcVal);
            }
        }
        $memcached->addServers($poolConnection);

        return $memcached;
    }
}
