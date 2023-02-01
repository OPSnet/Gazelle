<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class CacheTest extends TestCase {
    public function testCache() {
        $cache = new Gazelle\Cache;
        $this->assertCount(0, $cache->hitList(), 'cache-hit-list-empty');
        $this->assertCount(0, $cache->deleteList(), 'cache-del-list-empty');

        $prefix = 'unit-test-' . randomString(10) . '-';
        $key = "$prefix-1";
        $cache->cache_value($key, 'first', 600);
        $this->assertEquals('first', $cache->get_value($key), 'cache-get-1');
        $fetch = $cache->get_value($key);
        $this->assertCount(1, $cache->hitList(), 'cache-hit-list-1');
        $this->assertEquals([$key => 2], $cache->hitList(), 'cache-hit-list-1');

        $cache->delete_value($key);
        $this->assertFalse($cache->get_value($key), 'cache-delete-1');
        $this->assertCount(1, $cache->hitList(), 'cache-delete-count');
        $this->assertEquals(1, $cache->deleteListTotal(), 'cache-del-list-1');

        $key2 = "$prefix-2";
        $cache->cache_value($key2, 0, 600);
        $this->assertEquals(0, $cache->get_value($key2), 'cache-cache-2');
        $this->assertEquals(1, $cache->increment_value($key2), 'cache-incr-2');
        $this->assertEquals(1, $cache->get_value($key2), 'cache-increment-2');

        $key3 = "$prefix-3";
        $cache->cache_value($key3, 9, 600);
        $this->assertEquals(9, $cache->get_value($key3), 'cache-cache-3');
        $this->assertEquals(8, $cache->decrement_value($key3), 'cache-decr-3');
        $this->assertEquals(8, $cache->get_value($key3), 'cache-decrement-3');

        $multi = $cache->delete_multi([$key2, $key3]);
        $this->assertEquals([$key2 => true, $key3 => true], $multi, 'cache-multi-del');

        $key4 = "$prefix-4";
        $this->assertFalse($cache->get_value($key4), 'cache-unset-4');

        $key5 = "$prefix-5";
        $this->assertTrue($cache->setMulti([$key4 => 'k4', $key5 => 'k5']), 'cache-set-multi');
        $this->assertEquals('k4', $cache->get_value($key4), 'cache-get-4');
        $this->assertEquals('k5', $cache->get_value($key5), 'cache-get-5');
        $this->assertEquals([$key4 => 'k4', $key5 => 'k5'], $cache->getMulti([$key4, $key5]), 'cache-get-multi');

        $this->assertIsArray($cache->server_status(), 'cache-server-status');

        $html = Gazelle\Util\Twig::factory()->render('debug/cache.twig', ['cache' => $cache]);
        $this->assertStringContainsString('<table id="debug_cache" class="debug_table hidden">', $html, 'cache-debug-render');
    }
}
