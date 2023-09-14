<?php

namespace Gazelle\Util;

class CacheVector extends \Gazelle\Base {
    /**
     * A simple bit vector implementation to handle lookups on a large range of
     * values. The idea is to use a string and map each value as a bit within a
     * byte of that string. No provision is made for clearing a bit that is set,
     * the solution is to flush the bit vector and reinitialize it.
     *
     * A vector does not know how to initialize itself, it can only report
     * whether it was able to reload from the cache.
     *
     * The initial use case is to represent the snatches a user has made.
     */

    protected string $bitvec;
    protected bool   $empty;

    public function __construct(
        protected readonly string $key,
        protected readonly int $length,
        protected int $expiry
    ) {
        $bitvec = self::$cache->get_value($this->key);
        if ($bitvec === false) {
            $this->bitvec = str_repeat(chr(0), $this->length);
            $this->empty  = true;
        } else {
            $this->bitvec = $bitvec;
            $this->empty  = false;
        }
    }

    public function flush(): static {
        self::$cache->delete_value($this->key);
        $this->bitvec = str_repeat(chr(0), $this->length);
        $this->empty  = true;
        return $this;
    }

    public function persist(): static {
        self::$cache->cache_value($this->key, $this->bitvec, $this->expiry);
        return $this;
    }

    public function isEmpty(): bool {
        return $this->empty;
    }

    /**
     * Initialize a vector. For a vector length of 10, we might be setting the
     * values of [1 .. 10] or [11 .. 20] or [1341 .. 1350] etc. The code
     * therefore has to subtract the offset (0, 10, 1340 respectively) so that
     * the appropriate bit gets flipped in the range 1..10
     *
     * @return int number of values set (only real use is for debugging)
     */
    public function init(int $offset, array $list): int {
        $total = 0;
        foreach ($list as $value) {
            if ($value && $this->set($value - $offset)) {
                ++$total;
            }
        }
        if ($total) {
            $this->empty = false;
            $this->persist();
        }
        return $total;
    }

    /**
     * Set a value in the vector
     *
     * @return bool false if the value was out of range
     */
    public function set(int $value): bool {
        $offset = (int)floor($value / 8);
        if ($offset < 0 || $offset > $this->length - 1)  {
            return false;
        }
        $source = ord(substr($this->bitvec, $offset, 1));
        $mask   = 1 << ($value % 8);
        $this->bitvec = substr_replace($this->bitvec, chr($source | $mask), $offset, 1);
        return true;
    }

    /**
     * Query a value in the vector
     *
     * @return bool true if value is set, otherwise false if not set OR the value was out of range
     */
    public function get(int $value): bool {
        $offset = (int)floor($value / 8);
        if ($offset < 0 || $offset > $this->length - 1)  {
            return false;
        }
        $mask   = 1 << ($value % 8);
        return (bool)(ord(substr($this->bitvec, $offset, 1)) & $mask);
    }
}
