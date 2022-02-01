<?php

namespace Gazelle\Manager;

class Bonus extends \Gazelle\Base {
    const CACHE_ITEM = 'bonus_item';
    const CACHE_OPEN_POOL = 'bonus_pool'; // also defined in \Gazelle\Bonus

    protected array $items;

    /**
     * Return the global discount rate for the shop
     *
     * @return int Discount rate (0: normal price, 100: everything is free :)
     */
    public function discount(): int {
        return (int)(new \Gazelle\Manager\SiteOption)->findValueByName('bonus-discount') ?? 0;
    }

    public function itemList(): array {
        if (!isset($this->items)) {
            $items = self::$cache->get_value(self::CACHE_ITEM);
            if ($items === false) {
                $discount = $this->discount();
                self::$db->prepared_query("
                    SELECT ID,
                     Price * (greatest(0, least(100, 100 - ?)) / 100) as Price,
                        Amount, MinClass, FreeClass, Label, Title, sequence,
                        IF (Label REGEXP '^other-', 'NoOp', 'ConfirmPurchase') AS JS_on_click,
                        IF (Label REGEXP '^title-bb-[yn]', 'NoOp', 'ConfirmPurchase') AS JS_on_click
                    FROM bonus_item
                    ORDER BY sequence
                    ", $discount
                );
                $items = self::$db->to_array('Label', MYSQLI_ASSOC, false);
                self::$cache->cache_value(self::CACHE_ITEM, $items, 0);
            }
            $this->items = $items;
        }
        return $this->items;
    }

    public function flushPriceCache() {
        $this->items = [];
        self::$cache->delete_value(self::CACHE_ITEM);
    }

    public function getOpenPool(): array {
        $key = self::CACHE_OPEN_POOL;
        $pool = self::$cache->get_value($key);
        if ($pool === false) {
            $pool = self::$db->rowAssoc("
                SELECT bonus_pool_id, name, total
                FROM bonus_pool
                WHERE now() BETWEEN since_date AND until_date
                ORDER BY since_date
                LIMIT 1
            ") ?? [];
            self::$cache->cache_value($key, $pool, 3600);
        }
        return $pool;
    }

    public function addMultiPoints(int $points, array $ids = []): int {
        if (empty($ids)) {
            return 0;
        }
        self::$db->prepared_query("
            UPDATE user_bonus SET
                points = points + ?
            WHERE user_id in (" . placeholders($ids) . ")
            ", $points, ...$ids
        );
        self::$cache->deleteMulti(array_map(fn($k) => "user_stats_$k", $ids));
        self::$cache->deleteMulti(array_map(fn($k) => "u_$k", $ids));
        return self::$db->affected_rows();
    }

    public function addGlobalPoints(int $points): int {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main um
                AND um.Enabled = '1'
                AND NOT EXISTS (
                    SELECT 1 FROM user_has_attr uha
                    INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID AND ua.Name IN ('disable-bonus-points', 'no-fl-gifts'))
                    WHERE uha.UserID = um.ID
                )
        ");
        return $this->addMultiPoints($points, self::$db->collect('ID', false));
    }

    public function addActivePoints(int $points, string $since): int {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main um
            INNER JOIN user_last_access ula ON (ula.user_id = um.ID)
                AND um.Enabled = '1'
                AND NOT EXISTS (
                    SELECT 1 FROM user_has_attr uha
                    INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID AND ua.Name IN ('disable-bonus-points', 'no-fl-gifts'))
                    WHERE uha.UserID = um.ID
                )
                AND ula.last_access >= ?
            ", $since
        );
        return $this->addMultiPoints($points, self::$db->collect('ID', false));
    }

    public function addUploadPoints(int $points, string $since): int {
        self::$db->prepared_query($sql = "
            SELECT DISTINCT um.ID
            FROM users_main um
            INNER JOIN torrents t ON (t.UserID = um.ID)
                AND um.Enabled = '1'
                AND NOT EXISTS (
                    SELECT 1 FROM user_has_attr uha
                    INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID AND ua.Name IN ('disable-bonus-points', 'no-fl-gifts'))
                    WHERE uha.UserID = um.ID
                )
                AND t.Time >= ?
            ", $since
        );
        return $this->addMultiPoints($points, self::$db->collect('ID', false));
    }

    public function addSeedPoints(int $points): int {
        self::$db->prepared_query("
            SELECT DISTINCT um.ID
            FROM users_main um
            INNER JOIN xbt_files_users xfu ON (xfu.uid = um.ID)
                AND um.Enabled = '1'
                AND NOT EXISTS (
                    SELECT 1 FROM user_has_attr uha
                    INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID AND ua.Name IN ('disable-bonus-points', 'no-fl-gifts'))
                    WHERE uha.UserID = um.ID
                )
                AND xfu.active = 1 and xfu.remaining = 0 and xfu.connectable = 1 and timespent > 0
        ");
        return $this->addMultiPoints($points, self::$db->collect('ID', false));
    }

    public function givePoints(\Gazelle\Schedule\Task $task = null) {
        //------------------------ Update Bonus Points -------------------------//
        // calculation:
        // Size * (0.0754 + (0.1207 * ln(1 + seedtime)/ (seeders ^ 0.55)))
        // Size (convert from bytes to GB) is in torrents
        // Seedtime (convert from hours to days) is in xbt_files_history
        // Seeders is in torrents_leech_stats

        self::$db->prepared_query("
            CREATE TEMPORARY TABLE bonus_update (
                user_id int(11) unsigned NOT NULL PRIMARY KEY,
                delta float(20, 5) NOT NULL
            )
        ");
        self::$db->prepared_query("
            SET SESSION tx_isolation = 'READ-UNCOMMITTED'
        ");
        self::$db->prepared_query("
            INSERT INTO bonus_update (user_id, delta)
            SELECT xfu.uid,
                sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders))
            FROM xbt_files_users            AS xfu
            INNER JOIN xbt_files_history    AS xfh USING (uid, fid)
            INNER JOIN users_main           AS um ON (um.ID = xfu.uid)
            INNER JOIN torrents             AS t  ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
            WHERE xfu.active         = 1
                AND xfu.remaining    = 0
                AND xfu.mtime        > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND um.Enabled       = '1'
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
                AND NOT EXISTS (
                    SELECT 1 FROM user_has_attr uha
                    INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID AND ua.Name IN ('disable-bonus-points', 'no-fl-gifts'))
                    WHERE uha.UserID = um.ID
                )
            GROUP BY xfu.uid
        ");
        self::$db->prepared_query("
            SET SESSION tx_isolation = 'REPEATABLE-READ'
        ");
        if ($task) {
            $task->info('bonus_update table constructed');
        }

        self::$db->prepared_query("
            INSERT INTO user_bonus
                     (user_id, points)
            SELECT bu.user_id, bu.delta
            FROM bonus_update bu
            ON DUPLICATE KEY UPDATE points = points + bu.delta
        ");
        $processed = self::$db->affected_rows();
        if ($task) {
            $task->info('user_bonus updated');
        }

        /* flush their stats */
        self::$db->prepared_query("
            SELECT concat('u_', bu.user_id) FROM bonus_update bu
        ");
        if (self::$db->has_results()) {
            self::$cache->deleteMulti(self::$db->collect(0, false));
        }

        return $processed;
    }
}
