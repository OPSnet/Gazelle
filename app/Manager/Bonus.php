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

    public function itemList() {
        if (!isset($this->items)) {
            $items = $this->cache->get_value(self::CACHE_ITEM);
            if ($items === false) {
                $discount = $this->discount();
                $this->db->prepared_query("
                    SELECT ID,
                     Price * (greatest(0, least(100, 100 - ?)) / 100) as Price,
                        Amount, MinClass, FreeClass, Label, Title, sequence,
                        IF (Label REGEXP '^other-', 'ConfirmOther', 'null') AS JS_next_function,
                        IF (Label REGEXP '^title-bb-[yn]', 'NoOp', 'ConfirmPurchase') AS JS_on_click
                    FROM bonus_item
                    ORDER BY sequence
                    ", $discount
                );
                $items = $this->db->to_array('Label', MYSQLI_ASSOC, false);
                $this->cache->cache_value(self::CACHE_ITEM, $items, 0);
            }
            $this->items = $items;
        }
        return $this->items;
    }

    public function flushPriceCache() {
        $this->items = [];
        $this->cache->delete_value(self::CACHE_ITEM);
    }

    public function getOpenPool() {
        $key = self::CACHE_OPEN_POOL;
        $pool = $this->cache->get_value($key);
        if ($pool === false) {
            $pool = $this->db->rowAssoc("
                SELECT bonus_pool_id, name, total
                FROM bonus_pool
                WHERE now() BETWEEN since_date AND until_date
                ORDER BY since_date
                LIMIT 1
            ") ?? [];
            $this->cache->cache_value($key, $pool, 3600);
        }
        return $pool;
    }

    public function addMultiPoints(int $points, array $ids = []): int {
        $this->db->prepared_query("
            UPDATE user_bonus SET
                points = points + ?
            WHERE user_id in (" . placeholders($ids) . ")
            ", $points, ...$ids
        );
        $this->cache->deleteMulti(array_map(fn($k) => "user_stats_$k", $ids));
        return $this->db->affected_rows();
    }

    public function addGlobalPoints(int $points): int {
        $this->db->prepared_query("
            SELECT um.ID
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
            LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = 'no-fl-gifts')
            WHERE ui.DisablePoints = '0'
                AND um.Enabled = '1'
                AND uhafl.UserID IS NULL
        ");
        return $this->addMultiPoints($points, $this->db->collect('ID', false));
    }

    public function addActivePoints(int $points, string $since): int {
        $this->db->prepared_query("
            SELECT um.ID
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            INNER JOIN user_last_access ula ON (ula.user_id = um.ID)
            LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
            LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = 'no-fl-gifts')
            WHERE ui.DisablePoints = '0'
                AND um.Enabled = '1'
                AND uhafl.UserID IS NULL
                AND ula.last_access >= ?
            ", $since
        );
        return $this->addMultiPoints($points, $this->db->collect('ID', false));
    }

    public function addUploadPoints(int $points, string $since): int {
        $this->db->prepared_query($sql = "
            SELECT DISTINCT um.ID
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            INNER JOIN torrents t ON (t.UserID = um.ID)
            LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
            LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = 'no-fl-gifts')
            WHERE ui.DisablePoints = '0'
                AND um.Enabled = '1'
                AND uhafl.UserID IS NULL
                AND t.Time >= ?
            ", $since
        );
        return $this->addMultiPoints($points, $this->db->collect('ID', false));
    }

    public function addSeedPoints(int $points): int {
        $this->db->prepared_query("
            SELECT DISTINCT um.ID
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            INNER JOIN xbt_files_users xfu ON (xfu.uid = um.ID)
            LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
            LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = 'no-fl-gifts')
            WHERE ui.DisablePoints = '0'
                AND um.Enabled = '1'
                AND uhafl.UserID IS NULL
                AND xfu.active = 1 and xfu.remaining = 0 and xfu.connectable = 1 and timespent > 0
        ");
        return $this->addMultiPoints($points, $this->db->collect('ID', false));
    }

    public function givePoints(\Gazelle\Schedule\Task $task = null) {
        //------------------------ Update Bonus Points -------------------------//
        // calcuation:
        // Size * (0.0754 + (0.1207 * ln(1 + seedtime)/ (seeders ^ 0.55)))
        // Size (convert from bytes to GB) is in torrents
        // Seedtime (convert from hours to days) is in xbt_snatched
        // Seeders is in torrents

        // precalculate the users we update this run
        if ($task) {
            $task->debug('begin');
        } else {
            echo "begin\n";
        }
        $this->db->prepared_query("
            CREATE TEMPORARY TABLE xbt_unique (
                uid int(11) NOT NULL,
                fid int(11) NOT NULL,
                PRIMARY KEY (uid, fid)
            )
            SELECT DISTINCT uid, fid
            FROM xbt_files_users xfu
            INNER JOIN users_main AS um ON (um.ID = xfu.uid)
            INNER JOIN users_info AS ui ON (ui.UserID = xfu.uid)
            INNER JOIN torrents   AS t  ON (t.ID = xfu.fid)
            WHERE xfu.active = 1
                AND xfu.remaining = 0
                AND xfu.mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND um.Enabled = '1'
                AND ui.DisablePoints = '0'
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
        ");
        if ($task) {
            $task->debug('xbt_unique constructed');
        } else {
            echo "xbt_unique constructed\n";
        }

        $userId = 1;
        $chunk = 150;
        $processed = 0;
        $more = true;
        while ($more) {
            /* update a block of users at a time, to minimize locking contention */
            $this->db->prepared_query("
                INSERT INTO user_bonus
                SELECT
                    xfu.uid AS ID,
                    sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders)) as new
                FROM xbt_unique xfu
                INNER JOIN xbt_files_history AS xfh USING (uid, fid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                WHERE xfu.uid BETWEEN ? AND ?
                    AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
                GROUP BY
                    xfu.uid
                ON DUPLICATE KEY UPDATE points = points + VALUES(points)
                ", $userId, $userId + $chunk - 1
            );
            $processed += $this->db->affected_rows();

            /* flush their stats */
            $this->db->prepared_query("
                SELECT concat('user_stats_', xfu.uid) as ck
                FROM xbt_unique xfu
                WHERE xfu.uid BETWEEN ? AND ?
                ", $userId, $userId + $chunk - 1
            );
            if ($this->db->has_results()) {
                $this->cache->deleteMulti($this->db->collect('ck', false));
            }
            if ($task) {
                $task->debug('chunk done', $userId);
            } else {
                echo "chunk done $userId\n";
            }

            /* see if there are some more users to process */
            $userId += $chunk;
            $more = $this->db->scalar("
                SELECT 1 FROM xbt_unique WHERE uid >= ?
                ", $userId
            );
        }
        return $processed;
    }
}
