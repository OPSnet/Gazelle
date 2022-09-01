<?php

namespace Gazelle\Collage;

abstract class AbstractCollage extends \Gazelle\Base {

    protected \Gazelle\Collage $holder;

    protected int   $id; // hold a local copy of our ID to save time
    protected array $artists      = [];
    protected array $contributors = [];

    abstract public function entryTable(): string;
    abstract public function entryColumn(): string;
    abstract public function entryList(): array;
    abstract public function load(): int;
    abstract protected function flushTarget(int $targetId): void;

    public function __construct(\Gazelle\Collage $holder) {
        $this->holder = $holder;
        $this->id     = $holder->id();
    }

    public function artistList(): array {
        return $this->artists;
    }

    public function contributorList(): array {
        return $this->contributors;
    }

    /**
     * Does the entry already exist in this collage
     */
    public function hasEntry(int $entryId): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM {$this->entryTable()}
            WHERE CollageID = ?  AND {$this->entryColumn()} = ?
            ", $this->id, $entryId
        );
    }

    public function entryUserId(int $entryId): int {
        return (int)self::$db->scalar("
            SELECT UserID FROM {$this->entryTable()}
            WHERE CollageID = ?
                AND {$this->entryColumn()} = ?
            ", $this->id, $entryId
        );
    }

    /**
     * Flush the cache keys associated with this collage.
     */
    public function flushAll(array $keys = []) {
        self::$db->prepared_query("
            SELECT concat('collage_subs_user_new_', UserID)
            FROM users_collage_subs
            WHERE CollageID = ?
            ", $this->id
        );
        if (self::$db->has_results()) {
            array_push($keys, ...self::$db->collect(0, false));
        }
        self::$cache->deleteMulti($keys);
        $this->holder->flush();
        return $this;
    }

    /**
     * Update the database with the correct number of entries in this collage.
     * The caller of this method is responsible for invalidating the cache so
     * that the next instantiation will pick up the new value.
     *
     * @return int Number of entries
     */
    protected function recalcTotal(): int {
        self::$db->prepared_query("
            UPDATE collages SET
                updated = now(),
                NumTorrents = (SELECT count(*) FROM {$this->entryTable()} ca WHERE ca.CollageID = ?)
            WHERE ID = ?
            ", $this->id, $this->id
        );
        return self::$db->scalar("
            SELECT count(*) FROM {$this->entryTable()} ca WHERE ca.CollageID = ?
            ", $this->id
        );
    }

    /**
     * Add an entry to a collage.
     */
    public function addEntry(int $entryId, int $userId): int {
        if ($this->hasEntry($entryId)) {
            return 0;
        }
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT IGNORE INTO {$this->entryTable()}
                   (CollageID, UserID, {$this->entryColumn()}, Sort)
            VALUES (?,         ?,      ?,
                (SELECT coalesce(max(ca.Sort), 0) + 10 FROM {$this->entryTable()} ca WHERE ca.CollageID = ?)
            )
            ",  $this->id, $userId, $entryId, $this->id
        );
        $affected = self::$db->affected_rows();
        if ($affected === 0) {
            self::$db->commit();
            return 0;
        }
        $this->recalcTotal();
        $this->flushTarget($entryId);
        self::$db->commit();
        return $affected;
    }

    /**
     * Remove an entry from a collage
     */
   public function removeEntry(int $entryId): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM {$this->entryTable()}
            WHERE CollageID = ?
                AND {$this->entryColumn()} = ?
            ", $this->id, $entryId
        );
        $affected = self::$db->affected_rows();
        if ($affected === 0) {
            self::$db->commit();
            return 0;
        }
        $this->recalcTotal();
        self::$db->commit();
        $this->flushTarget($entryId);
        return $affected;
    }

    public function updateSequence(string $series): int {
        $series = $this->parseUrlArgs($series, 'li[]');
        if (empty($series)) {
            return 0;
        }
        self::$db->prepared_query("
            SELECT {$this->entryColumn()} AS cID,
                UserID
            FROM {$this->entryTable()}
            WHERE CollageID = ?
            ", $this->id
        );
        $userMap = self::$db->to_pair('cID', 'UserID');
        $id = $this->id;
        $args = array_merge(...array_map(function ($sort, $entryId) use ($id, $userMap) {
            return [(int)$entryId, ($sort + 1) * 10, $id, $userMap[$entryId]];
        }, array_keys($series), $series));
        self::$db->prepared_query("
            INSERT INTO {$this->entryTable()} ({$this->entryColumn()}, Sort, CollageID, UserID)
            VALUES " . implode(', ', array_fill(0, count($series), '(?, ?, ?, ?)')) . "
            ON DUPLICATE KEY UPDATE Sort = VALUES(Sort)
            ", ...$args
        );
        return self::$db->affected_rows();
    }

    public function updateSequenceEntry(int $entryId, int $sequence): int {
        self::$db->prepared_query("
            UPDATE {$this->entryTable()} SET
                Sort = ?
            WHERE CollageID = ?
                AND {$this->entryColumn()} = ?
            ", $sequence, $this->id, $entryId
        );
        return self::$db->affected_rows();
    }

   public function remove(): int {
        self::$db->prepared_query("
            UPDATE collages SET
                Deleted = '1'
            WHERE Deleted = '0'
                AND ID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Hydrate an array from a query string (everything that follow '?')
     * This reimplements parse_str() and side-steps the issue of max_input_vars limits.
     *
     * Example:
     * in: li[]=14&li[]=31&li[]=58&li[]=68&li[]=69&li[]=54&li[]=5, param=li[]
     * parsed: ['li[]' => ['14', '31, '58', '68', '69', '5']]
     * out: ['14', '31, '58', '68', '69', '5']
     *
     * @param string $urlArgs query string from url
     * @param string $param url param to extract
     * @return array hydrated equivalent
     */
    protected function parseUrlArgs(string $urlArgs, string $param): array {
        if (empty($urlArgs)) {
            return [];
        }
        $list = [];
        $pairs = explode('&', $urlArgs);
        foreach ($pairs as $p) {
            [$name, $value] = explode('=', $p, 2);
            if (!isset($list[$name])) {
                $list[$name] = $value;
            } else {
                if (!is_array($list[$name])) {
                    $list[$name] = [$list[$name]];
                }
                $list[$name][] = $value;
            }
        }
        return array_key_exists($param, $list) ? $list[$param] : [];
    }
}
