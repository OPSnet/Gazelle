<?php

namespace Gazelle;

/*************************************************************************|
|--------------- Caching class -------------------------------------------|
|*************************************************************************|

This class is a wrapper for the Memcache class, and it's been written in
order to better handle the caching of full pages with bits of dynamic
content that are different for every user.

As this inherits memcache, all of the default memcache methods work -
however, this class has page caching functions superior to those of
memcache.

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

    protected array $CacheHits = [];
    protected array $ClearedKeys = [];
    protected array $Servers = [];
    protected array $MemcacheDBArray = [];
    protected string $MemcacheDBKey = '';

    protected bool $InTransaction = false;
    protected bool $CanClear = false;
    protected bool $InternalCache = true;

    protected string $PersistentKeysRegexp =
        '/^(?:global_notification$|(?:ajax_requests|notifications_one_reads_|query_lock|stats|top10(?:tor|votes)|users_snatched)_)/';

    public $Time = 0;

    /**
     * Constructor. Takes a array of $Servers with a host, port, and optionally a weight.
     * We then add each of the servers in the array to our memcached pool assuming we haven't
     * already connected to it before (cross-checking against the pool's server list). If you want
     * to connect to a socket, you need to use port 0, though internally in the pool it'll have
     * port 11211, so if using a server with port 0, we also need to check for port 11211 in
     * the $ServerList as Memcached really doesn't like the same server being added hundreds of time
     * with the same weight.
     *
     * @see Memcached::getServerList()
     */
    public function __construct() {
        parent::__construct(CACHE_ID);
        $this->Servers = MEMCACHE_HOST_LIST;
        $ServerList = [];
        foreach ($this->getServerList() as $Server) {
            $ServerList["{$Server['host']}:{$Server['port']}"] = true;
        }
        foreach ($this->Servers as $Server) {
            $ServerCheck = isset($ServerList["{$Server['host']}:{$Server['port']}"]);
            if ($Server['port'] == 0) {
                $ServerCheck = $ServerCheck || isset($ServerList["{$Server['host']}:11211"]);
            }
            if (!$ServerCheck) {
                $Weight = (isset($Server['weight'])) ? $Server['weight'] : 0;
                $this->addServer($Server['host'], $Server['port'], $Weight);
            }
        }
    }

    public function enableCacheClear() {
        $this->CanClear = true;
        return $this;
    }

    public function disableCacheClear() {
        $this->CanClear = false;
        return $this;
    }

    public function enableLocalCache() {
        $this->InternalCache = true;
        return $this;
    }

    public function disableLocalCache() {
        $this->InternalCache = false;
        return $this;
    }

    public function hits(): array {
        return $this->CacheHits;
    }

    //---------- Caching functions ----------//

    // Wrapper for Memcache::set, with the zlib option removed and default duration of 30 days
    public function cache_value($Key, $Value, $Duration = 2592000) {
        $StartTime = microtime(true);
        if (empty($Key)) {
            trigger_error("Cache insert failed for empty key");
        }
        if (!$this->set($Key, $Value, $Duration)) {
            trigger_error("Cache insert failed for key $Key:" . $this->getResultMessage());
        }
        if ($this->InternalCache && array_key_exists($Key, $this->CacheHits)) {
            $this->CacheHits[$Key] = $Value;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }

    // Wrapper for Memcache::add, with the zlib option removed and default duration of 30 days
    public function add_value($Key, $Value, $Duration = 2592000) {
        $StartTime = microtime(true);
        $Added = $this->add($Key, $Value, $Duration);
        $this->Time += (microtime(true) - $StartTime) * 1000;
        return $Added;
    }

    public function replace_value($Key, $Value, $Duration = 2592000) {
        $StartTime = microtime(true);
        $this->replace($Key, $Value, $Duration);
        if ($this->InternalCache && array_key_exists($Key, $this->CacheHits)) {
            $this->CacheHits[$Key] = $Value;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }

    public function get_value($Key, $NoCache = false) {
        if (!$this->InternalCache) {
            $NoCache = true;
        }
        $StartTime = microtime(true);
        if (empty($Key)) {
            trigger_error('Cache retrieval failed for empty key');
        }

        if (!empty($_GET['clearcache']) && $this->CanClear && !isset($this->ClearedKeys[$Key]) && !preg_match($this->PersistentKeysRegexp, $Key)) {
            if ($_GET['clearcache'] === '1') {
                if (count($this->CacheHits) > 0) {
                    foreach (array_keys($this->CacheHits) as $HitKey) {
                        if (!isset($this->ClearedKeys[$HitKey]) && !preg_match($this->PersistentKeysRegexp, $Key)) {
                            $this->delete($HitKey);
                            unset($this->CacheHits[$HitKey]);
                            $this->ClearedKeys[$HitKey] = true;
                        }
                    }
                }
                $this->delete($Key);
                $this->Time += (microtime(true) - $StartTime) * 1000;
                return false;
            } elseif ($_GET['clearcache'] == $Key) {
                $this->delete($Key);
                $this->Time += (microtime(true) - $StartTime) * 1000;
                return false;
            } elseif (substr($_GET['clearcache'], -1) === '*') {
                $Prefix = substr($_GET['clearcache'], 0, -1);
                if ($Prefix === '' || $Prefix === substr($Key, 0, strlen($Prefix))) {
                    $this->delete($Key);
                    $this->Time += (microtime(true) - $StartTime) * 1000;
                    return false;
                }
            }
            $this->ClearedKeys[$Key] = true;
        }

        // For cases like the forums, if a key is already loaded, grab the existing pointer
        if (isset($this->CacheHits[$Key]) && !$NoCache) {
            $this->Time += (microtime(true) - $StartTime) * 1000;
            return $this->CacheHits[$Key];
        }

        $Return = $this->get($Key);
        if ($Return !== false) {
            $this->CacheHits[$Key] = $NoCache ? null : $Return;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
        return $Return;
    }

    // Wrapper for Memcache::delete. For a reason, see above.
    public function delete_value($Key) {
        $StartTime = microtime(true);
        if (empty($Key)) {
            trigger_error('Cache deletion failed for empty key');
        }
        $ret = $this->delete($Key);
        unset($this->CacheHits[$Key]);
        $this->Time += (microtime(true) - $StartTime) * 1000;
        return $ret;
    }

    public function increment_value($Key, $Value = 1) {
        $StartTime = microtime(true);
        $NewVal = $this->increment($Key, $Value);
        if (isset($this->CacheHits[$Key])) {
            $this->CacheHits[$Key] = $NewVal;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }

    public function decrement_value($Key, $Value = 1) {
        $StartTime = microtime(true);
        $NewVal = $this->decrement($Key, $Value);
        if (isset($this->CacheHits[$Key])) {
            $this->CacheHits[$Key] = $NewVal;
        }
        $this->Time += (microtime(true) - $StartTime) * 1000;
    }

    //---------- memcachedb functions ----------//

    public function begin_transaction($Key) {
        $Value = $this->get($Key);
        if (!is_array($Value)) {
            $this->InTransaction = false;
            $this->MemcacheDBKey = '';
            return false;
        }
        $this->MemcacheDBArray = $Value;
        $this->MemcacheDBKey = $Key;
        $this->InTransaction = true;
        return true;
    }

    public function cancel_transaction() {
        $this->InTransaction = false;
        $this->MemcacheDBKey = '';
    }

    public function commit_transaction($Time = 2592000) {
        if (!$this->InTransaction) {
            return false;
        }
        $this->cache_value($this->MemcacheDBKey, $this->MemcacheDBArray, $Time);
        $this->InTransaction = false;
    }

    // Updates multiple rows in an array
    public function update_transaction($Rows, $Values) {
        if (!$this->InTransaction) {
            return false;
        }
        $Array = $this->MemcacheDBArray;
        if (is_array($Rows)) {
            $i = 0;
            $Keys = $Rows[0];
            $Property = $Rows[1];
            foreach ($Keys as $Row) {
                $Array[$Row][$Property] = $Values[$i];
                $i++;
            }
        } else {
            $Array[$Rows] = $Values;
        }
        $this->MemcacheDBArray = $Array;
    }

    // Updates multiple values in a single row in an array
    // $Values must be an associative array with key:value pairs like in the array we're updating
    public function update_row($Row, $Values) {
        if (!$this->InTransaction) {
            return false;
        }
        if ($Row === false) {
            $UpdateArray = $this->MemcacheDBArray;
        } else {
            $UpdateArray = $this->MemcacheDBArray[$Row];
        }
        foreach ($Values as $Key => $Value) {
            if (!array_key_exists($Key, $UpdateArray)) {
                trigger_error('Bad transaction key ('.$Key.') for cache '.$this->MemcacheDBKey);
            }
            if ($Value === '+1') {
                if (!is_number($UpdateArray[$Key])) {
                    trigger_error('Tried to increment non-number ('.$Key.') for cache '.$this->MemcacheDBKey);
                }
                ++$UpdateArray[$Key]; // Increment value
            } elseif ($Value === '-1') {
                if (!is_number($UpdateArray[$Key])) {
                    trigger_error('Tried to decrement non-number ('.$Key.') for cache '.$this->MemcacheDBKey);
                }
                --$UpdateArray[$Key]; // Decrement value
            } else {
                $UpdateArray[$Key] = $Value; // Otherwise, just alter value
            }
        }
        if ($Row === false) {
            $this->MemcacheDBArray = $UpdateArray;
        } else {
            $this->MemcacheDBArray[$Row] = $UpdateArray;
        }
    }

    // Increments multiple values in a single row in an array
    // $Values must be an associative array with key:value pairs like in the array we're updating
    public function increment_row($Row, $Values) {
        if (!$this->InTransaction) {
            return false;
        }
        if ($Row === false) {
            $UpdateArray = $this->MemcacheDBArray;
        } else {
            $UpdateArray = $this->MemcacheDBArray[$Row];
        }
        foreach ($Values as $Key => $Value) {
            if (!array_key_exists($Key, $UpdateArray)) {
                trigger_error("Bad transaction key ($Key) for cache ".$this->MemcacheDBKey);
            }
            if (!is_number($Value)) {
                trigger_error("Tried to increment with non-number ($Key) for cache ".$this->MemcacheDBKey);
            }
            $UpdateArray[$Key] += $Value; // Increment value
        }
        if ($Row === false) {
            $this->MemcacheDBArray = $UpdateArray;
        } else {
            $this->MemcacheDBArray[$Row] = $UpdateArray;
        }
    }

    // Insert a value at the beginning of the array
    public function insert_front($Key, $Value) {
        if (!$this->InTransaction) {
            return false;
        }
        if ($Key === '') {
            array_unshift($this->MemcacheDBArray, $Value);
        } else {
            $this->MemcacheDBArray = [$Key=>$Value] + $this->MemcacheDBArray;
        }
    }

    // Insert a value at the end of the array
    public function insert_back($Key, $Value) {
        if (!$this->InTransaction) {
            return false;
        }
        if ($Key === '') {
            array_push($this->MemcacheDBArray, $Value);
        } else {
            $this->MemcacheDBArray = $this->MemcacheDBArray + [$Key=>$Value];
        }

    }

    public function insert($Key, $Value) {
        if (!$this->InTransaction) {
            return false;
        }
        if ($Key === '') {
            $this->MemcacheDBArray[] = $Value;
        } else {
            $this->MemcacheDBArray[$Key] = $Value;
        }
    }

    public function delete_row($Row) {
        if (!$this->InTransaction) {
            return false;
        }
        if (!isset($this->MemcacheDBArray[$Row])) {
            trigger_error("Tried to delete non-existent row ($Row) for cache ".$this->MemcacheDBKey);
        }
        unset($this->MemcacheDBArray[$Row]);
    }

    public function update($Key, $Rows, $Values, $Time = 2592000) {
        if (!$this->InTransaction) {
            $this->begin_transaction($Key);
            $this->update_transaction($Rows, $Values);
            $this->commit_transaction($Time);
        } else {
            $this->update_transaction($Rows, $Values);
        }
    }

    /**
     * Tries to set a lock. Expiry time is one hour to avoid indefinite locks
     *
     * @param string $LockName name on the lock
     * @return true if lock was acquired
     */
    public function get_query_lock($LockName) {
        return $this->add_value('query_lock_'.$LockName, 1, 3600);
    }

    /**
     * Remove lock
     *
     * @param string $LockName name on the lock
     */
    public function clear_query_lock($LockName) {
        $this->delete_value('query_lock_'.$LockName);
    }

    /**
     * Get cache server status
     *
     * @return array (host => bool status, ...)
     */
    public function server_status() {
        /*$Status = [];
        foreach ($this->Servers as $Server) {
            $Status["$Server[host]:$Server[port]"] = $this->getServerStatus($Server['host'], $Server['port']);
        }*/
        return $this->getStats();
    }
}
