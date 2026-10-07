<?php

namespace Kipchak\Driver\Memcached\Tests;

use DI\Container;
use Kipchak\Driver\Config\Config;
use Kipchak\Driver\Memcached\Memcached;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

/**
 * Talks to a real Memcached. Start one with:
 *   docker run -d --rm --name memcached -p 51211:11211 memcached:1.6-alpine
 * and point MEMCACHED_TEST_HOST / MEMCACHED_TEST_PORT at it (defaults: 127.0.0.1:51211).
 */
final class MemcachedConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        $host = getenv('MEMCACHED_TEST_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('MEMCACHED_TEST_PORT') ?: 51211);

        $probe = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if ($probe === false) {
            $this->markTestSkipped("No Memcached reachable at {$host}:{$port}");
        }
        fclose($probe);

        $container = new Container();
        Config::initialise($container);
        $container->set('drivers.config', [
            'kipchak.api' => ['name' => 'memcached-test'],
            'kipchak.memcached' => [
                'pools' => [
                    'cache' => [['host' => $host, 'port' => $port]],
                ],
            ],
        ]);
        Memcached::initialise($container);
    }

    public function testConnectionReturnsTheRawClient(): void
    {
        $connection = Memcached::connection('cache');
        $key = 'kipchak-memcached-test-' . bin2hex(random_bytes(4));

        $this->assertInstanceOf(\Memcached::class, $connection);
        $this->assertTrue($connection->add($key, 1, 5));
        $this->assertFalse($connection->add($key, 1, 5));
        $this->assertSame(\Memcached::RES_NOTSTORED, $connection->getResultCode());
        $connection->delete($key);
    }

    public function testConnectionAndGetDefaultToTheCachePool(): void
    {
        $this->assertSame(Memcached::connection('cache'), Memcached::connection());
        $this->assertSame(Memcached::get('cache'), Memcached::get());
    }

    /**
     * Pools used to share one persistent \Memcached instance, so building one pool reset the other's servers.
     */
    public function testPoolsKeepTheirOwnServers(): void
    {
        $container = $this->containerWith([
            'cache' => [['host' => '127.0.0.1', 'port' => 51211]],
            'sessions' => [['host' => '127.0.0.1', 'port' => 51212]],
        ]);

        $cache = $container->get('drivers.cache.memcached.cache.connection');
        $sessions = $container->get('drivers.cache.memcached.sessions.connection');

        $this->assertNotSame($cache, $sessions);
        $this->assertSame([51211], array_column($cache->getServerList(), 'port'));
        $this->assertSame([51212], array_column($sessions->getServerList(), 'port'));
    }

    public function testAdapterIsNamespacedWithTheApiName(): void
    {
        $adapter = Memcached::get('cache');
        $key = 'ns' . bin2hex(random_bytes(4));
        $item = $adapter->getItem($key);
        $item->set('value');
        $adapter->save($item);

        // Another adapter on the API's namespace sees the item; one on "1" (the old, broken namespace) does not.
        $client = Memcached::connection('cache');
        $this->assertTrue((new MemcachedAdapter($client, 'memcached-test'))->getItem($key)->isHit());
        $this->assertFalse((new MemcachedAdapter($client, '1'))->getItem($key)->isHit());
        $adapter->deleteItem($key);
    }

    public function testNamespaceFallsBackAndIsSanitised(): void
    {
        $this->assertSame('apiCache', TestMemcached::namespaceFor([]));
        $this->assertSame('My_API_v2', TestMemcached::namespaceFor(['name' => 'My API/v2']));
        $this->assertSame('memcached-test', TestMemcached::namespaceFor(['name' => 'memcached-test']));
    }

    private function containerWith(array $pools): Container
    {
        $container = new Container();
        Config::initialise($container);
        $container->set('drivers.config', [
            'kipchak.api' => ['name' => 'memcached-test'],
            'kipchak.memcached' => ['pools' => $pools],
        ]);
        Memcached::initialise($container);

        return $container;
    }

    public function testAdapterAndConnectionShareOneClient(): void
    {
        $adapter = Memcached::get('cache');
        $key = 'shared' . bin2hex(random_bytes(4));
        $item = $adapter->getItem($key);
        $item->set('value');
        $this->assertTrue($adapter->save($item));

        $this->assertTrue($adapter->getItem($key)->isHit());
        $this->assertGreaterThan(0, count(Memcached::connection('cache')->getServerList()));
        $adapter->deleteItem($key);
    }
}

final class TestMemcached extends Memcached
{
    public static function namespaceFor(array $apiConfig): string
    {
        return parent::namespaceFor($apiConfig);
    }
}
