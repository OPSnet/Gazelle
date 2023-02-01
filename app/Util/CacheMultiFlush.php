<?php

namespace Gazelle\Util;

class CacheMultiFlush extends \Gazelle\Base {
    /**
     * Flush a list of user cache keys. The shape is provided as sprintf format,
     * and each UserID is flushed for each shape.
     *
     * @param string $namespace Identify source table to be scanned
     * @param array $shape List of keys, e.g. ['u_%d', 'tg_%d']
     * @return int number of keys flushed
     */
    public function multiFlush(string $namespace, array $shape): int {
        $table = CACHE_DB[$namespace]['table'];
        $pk    = CACHE_DB[$namespace]['pk'];
        $max = self::$db->scalar("
            SELECT max($pk) FROM $table
        ");
        $flushed = 0;
        $current = 0;
        $step = (int)floor(CACHE_BULK_FLUSH / count($shape));
        while ($current < $max) {
            self::$db->prepared_query("
                SELECT $pk FROM $table WHERE $pk > ? ORDER BY $pk LIMIT ?
                ", $current, $step
            );
            $list = self::$db->collect(0);
            $flush = [];
            foreach ($shape as $s) {
                $flush = array_merge($flush, array_map(function ($id) use ($s) {return sprintf($s, $id);}, $list));
            }
            self::$cache->delete_multi($flush);
            $flushed += count($flush);
            $current = end($list);
        }
        return $flushed;
    }
}
