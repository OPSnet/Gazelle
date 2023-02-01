<?php

namespace Gazelle;

/*************************************************************************|
|--------------- Caching class -------------------------------------------|
|*************************************************************************|

This class is a wrapper for the Memcache class, and it's been written in
order to better handle the caching of full pages with bits of dynamic
content that are different for every user.

Also, Memcache::get and Memcache::set have been wrapped by
Gazelle\Cache::get_value and Gazelle\Cache::cache_value. get_value uses
the same argument as get, but cache_value only takes the key, the value,
and the duration (no zlib).

// Unix sockets
memcached -d -m 5120 -s /var/run/memcached.sock -a 0777 -t16 -C -u root

// TCP bind
memcached -d -m 8192 -l 10.10.0.1 -t8 -C

|*************************************************************************/

class Cache extends \Memcached {
    /**
     * Torrent Group cache version
     */
    const GROUP_VERSION = 6;

    protected array $hit;
    protected array $delete;
    protected float $elapsed;

    /**
     * Constructor. Takes a array of $Servers with a host, port, and optionally a weight.
     * We then add each of the servers in the array to our memcached pool assuming we haven't
     * already connected to it before (cross-checking against the pool's server list). If you want
     * to connect to a socket, you need to use port 0, though internally in the pool it'll have
     * port 11211, so if using a server with port 0, we also need to check for port 11211 in
     * the $ServerList as Memcached really doesn't like the same server being added hundreds of time
     * with the same weight.
     *
     * This looks far more complicated than it should be - Spine
     *
     * @see Memcached::getServerList()
     */
    public function __construct() {
        $begin = microtime(true);
        parent::__construct(CACHE_ID);
        $Servers = MEMCACHE_HOST_LIST;
        $ServerList = [];
        foreach ($this->getServerList() as $Server) {
            $ServerList["{$Server['host']}:{$Server['port']}"] = true;
        }
        foreach ($Servers as $Server) {
            $ServerCheck = isset($ServerList["{$Server['host']}:{$Server['port']}"]);
            if ($Server['port'] == 0) {
                $ServerCheck = $ServerCheck || isset($ServerList["{$Server['host']}:11211"]);
            }
            if (!$ServerCheck) {
                $Weight = (isset($Server['weight'])) ? $Server['weight'] : 0;
                $this->addServer($Server['host'], $Server['port'], $Weight);
            }
        }
        $this->elapsed = (microtime(true) - $begin) * 1000;
    }

    //---------- Caching functions ----------//

    // Wrapper for Memcache::set, with the zlib option removed and default duration of 30 days
    public function cache_value($Key, $value, $Duration = 2592000) {
        $begin = microtime(true);
        if (empty($Key)) {
            trigger_error("Cache insert failed for empty key");
        }
        if (!$this->set($Key, $value, $Duration)) {
            trigger_error("Cache insert failed for key $Key:" . $this->getResultMessage());
        }
        $this->elapsed += (microtime(true) - $begin) * 1000;
    }

    public function get_value(string $key) {
        $begin = microtime(true);
        if (empty($key)) {
            $value = false;
         } else {
            $value = $this->get($key);
            if (!isset($this->hit[$key])) {
                $this->hit[$key] = 0;
            }
            ++$this->hit[$key];
        }
        $this->elapsed += (microtime(true) - $begin) * 1000;
        return $value;
    }

    // Wrapper for Memcache::delete. For a reason, see above.
    public function delete_value(string $key) {
        $begin = microtime(true);
        if (empty($key)) {
            $ret = false;
        } else {
            $ret = $this->delete($key);
            if (!isset($this->delete)) {
                $this->delete = [];
            }
            $this->delete[] = $key;
        }
        $this->elapsed += (microtime(true) - $begin) * 1000;
        return $ret;
    }
    public function delete_multi(array $list) {
        $begin = microtime(true);
        if (empty($list)) {
            $ret = false;
        } else {
            $ret = $this->deleteMulti($list);
            if (!isset($this->delete)) {
                $this->delete = $list;
            } else {
                array_push($this->delete, ...$list);
            }
        }
        $this->elapsed += (microtime(true) - $begin) * 1000;
        return $ret;
    }

    public function increment_value(string $key, int $delta = 1): int {
        $begin = microtime(true);
        $new = $this->increment($key, $delta);
        if (!isset($this->hit[$key])) {
            $this->hit[$key] = 0;
        }
        ++$this->hit[$key];
        $this->elapsed += (microtime(true) - $begin) * 1000;
        return $new;
    }

    public function decrement_value($key, $delta = 1): int {
        $begin = microtime(true);
        $new = $this->decrement($key, $delta);
        if (!isset($this->hit[$key])) {
            $this->hit[$key] = 0;
        }
        ++$this->hit[$key];
        $this->elapsed += (microtime(true) - $begin) * 1000;
        return $new;
    }

    /**
     * Get cache server status
     *
     * @return array (host => bool status, ...)
     */
    public function server_status(): array {
        return $this->getStats();
    }

    public function elapsed(): float {
        return $this->elapsed;
    }

    public function deleteList(): array {
        $delete = $this->delete ?? [];
        sort($delete);
        return $delete;
    }

    public function deleteListTotal(): int {
        return count($this->delete ?? []);
    }

    public function hitList(): array {
        $hit = $this->hit ?? [];
        ksort($hit);
        return $hit;
    }

    public function hitListTotal(): int {
        return count($this->hit ?? []);
    }
}
