<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase {
    public function testCache(): void {
        $cache = new Cache();
        $this->assertCount(0, $cache->hitList(), 'cache-hit-list-empty');
        $this->assertCount(0, $cache->deleteList(), 'cache-del-list-empty');

        $prefix = 'unit-test-' . randomString(10) . '-';
        $key = "$prefix-1";
        $cache->cache_value($key, 'first', 600);
        $this->assertSame('first', $cache->get_value($key), 'cache-get-1');
        $this->assertCount(1, $cache->hitList(), 'cache-hit-list-total-1');
        $this->assertSame([$key => 1], $cache->hitList(), 'cache-hit-list-page-1');

        $cache->delete_value($key);
        $this->assertFalse($cache->get_value($key), 'cache-delete-1');
        $this->assertCount(1, $cache->hitList(), 'cache-delete-count'); /** @phpstan-ignore-line method.impossibleType */
        $this->assertSame(1, $cache->deleteListTotal(), 'cache-del-list-1');

        $key2 = "$prefix-2";
        $cache->cache_value($key2, 0, 600);
        $this->assertSame(0, $cache->get_value($key2), 'cache-cache-2');
        $this->assertSame(1, $cache->increment_value($key2), 'cache-incr-2');
        $this->assertSame(1, $cache->get_value($key2), 'cache-increment-2');

        $key3 = "$prefix-3";
        $cache->cache_value($key3, 9, 600);
        $this->assertSame(9, $cache->get_value($key3), 'cache-cache-3');
        $this->assertSame(8, $cache->decrement_value($key3), 'cache-decr-3');
        $this->assertSame(8, $cache->get_value($key3), 'cache-decrement-3');

        $multi = $cache->delete_multi([$key2, $key3]);
        $this->assertSame([$key2 => true, $key3 => true], $multi, 'cache-multi-del');

        $key4 = "$prefix-4";
        $this->assertFalse($cache->get_value($key4), 'cache-unset-4');

        $key5 = "$prefix-5";
        $this->assertTrue($cache->setMulti([$key4 => 'k4', $key5 => 'k5']), 'cache-set-multi');
        $this->assertSame('k4', $cache->get_value($key4), 'cache-get-4');
        $this->assertSame('k5', $cache->get_value($key5), 'cache-get-5');
        $this->assertSame([$key4 => 'k4', $key5 => 'k5'], $cache->getMulti([$key4, $key5]), 'cache-get-multi');

        $this->assertIsArray($cache->server_status(), 'cache-server-status'); /** @phpstan-ignore-line */

        $this->assertStringContainsString(
            "<details>\n<summary>5 Cache keys",
            Util\Twig::factory(new Manager\User())
                ->render('debug/cache.twig', ['cache' => $cache]),
            'cache-debug-render'
        );
    }
}
