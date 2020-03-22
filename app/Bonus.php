<?php

namespace Gazelle;

class Bonus {
    private $items;
    /** @var \DB_MYSQL */
    private $db;
    /** @var \CACHE */
    private $cache;

    const CACHE_ITEM = 'bonus_item';
    const CACHE_OPEN_POOL = 'bonus_pool';
    const CACHE_SUMMARY = 'bonus_summary.';
    const CACHE_HISTORY = 'bonus_history.';
    const CACHE_POOL_HISTORY = 'bonus_pool_history.';

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
        $this->items = $this->cache->get_value(self::CACHE_ITEM);
        if ($this->items === false) {
            $this->db->query("
                SELECT ID, Price, Amount, MinClass, FreeClass, Label, Title
                FROM bonus_item
                ORDER BY FIELD(label, 'token-1', 'token-4', 'token-2', 'token-3', 'other-1', 'other-4', 'other-2', 'other-3', 'title-bb-n', 'title-bb-y', 'title-off', 'invite')
            ");
            $this->items = $this->db->has_results() ? $this->db->to_array('Label') : [];
            $this->cache->cache_value(self::CACHE_ITEM, $this->items, 86400 * 30);
        }
    }

    public function getList() {
        return $this->items;
    }

    public function getItem($label) {
        return array_key_exists($label, $this->items) ? $this->items[$label] : null;
    }

    public function getTorrentValue($format, $media, $encoding, $haslogdb = 0, $logscore = 0, $logchecksum = 0) {
        if ($format == 'FLAC') {
            if ($media == 'CD' && $haslogdb && $logscore === 100 && $logchecksum == 1) {
                return BONUS_AWARD_FLAC_PERFECT;
            }
            elseif (in_array($media, ['Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'])) {
                return BONUS_AWARD_FLAC_PERFECT;
            }
            else {
                return BONUS_AWARD_FLAC;
            }
        }
        elseif ($format == 'MP3' && in_array($encoding, ['V2 (VBR)', 'V0 (VBR)', '320'])) {
            return BONUS_AWARD_MP3;
        }
        return BONUS_AWARD_OTHER;
    }

    public function getEffectivePrice($label, $effectiveClass) {
        $item  = $this->items[$label];
        return $effectiveClass >= $item['FreeClass'] ? 0 : $item['Price'];
    }

    public function getListOther($balance) {
        $list_other = [];
        foreach ($this->items as $label => $item) {
            if (preg_match('/^other-\d$/', $label) && $balance >= $item['Price']) {
                $list_other[] = [
                    'Label' => $item['Label'],
                    'Name'  => $item['Title'],
                    'Price' => $item['Price'],
                    'After' => $balance - $item['Price'],
                ];
            }
        }
        return $list_other;
    }

    public function getOpenPool() {
        $key = self::CACHE_OPEN_POOL;
        $pool = $this->cache->get_value($key);
        if ($pool === false) {
            $this->db->prepared_query('SELECT Id, Name, Total FROM bonus_pool WHERE now() BETWEEN SinceDate AND UntilDate');
            $pool = $this->db->next_record();
            $this->cache->cache_value($key, $pool, 3600);
        }
        return $pool;
    }

    public function donate($poolId, $value, $userId, $effectiveClass) {
        if ($effectiveClass < 250) {
            $taxedValue = $value * BONUS_POOL_TAX_STD;
        }
        elseif($effectiveClass == 250 /* Elite */) {
            $taxedValue = $value * BONUS_POOL_TAX_ELITE;
        }
        elseif($effectiveClass <= 500 /* EliteTM */) {
            $taxedValue = $value * BONUS_POOL_TAX_TM;
        }
        else {
            $taxedValue = $value * BONUS_POOL_TAX_STAFF;
        }

        $this->db->begin_transaction();
        if (!$this->removePoints($fromID, $price)) {
            $this->db->rollback();
            return false;
        }

        $pool = new \Gazelle\BonusPool($this->db, $this->cache, $poolId);
        $pool->contribute($userId, $value, $taxedValue);
        $this->db->commit();

        $this->cache->delete_value(self::CACHE_OPEN_POOL);
        $this->cache->delete_value(self::CACHE_POOL_HISTORY . $userId);
        $this->cache->delete_value('user_stats_' . $userId);
        $this->cache->delete_value('user_info_heavy_' . $userId);
        return true;
    }

    public function getUserSummary($userId) {
        $key = self::CACHE_SUMMARY . $userId;
        $summary = $this->cache->get_value($key);
        if ($summary === false) {
            $this->db->prepared_query('
                SELECT count(*) AS nr, coalesce(sum(price), 0) AS total FROM bonus_history WHERE UserID = ?
                ', $userId
            );
            $summary = $this->db->next_record(MYSQLI_ASSOC);
            $this->cache->cache_value($key, $summary, 86400 * 7);
        }
        return $summary;
    }

    public function getUserHistory($userId, $page, $itemsPerPage) {
        $key = self::CACHE_HISTORY . "{$userId}.{$page}";
        $history = $this->cache->get_value($key);
        if ($history === false) {
            $this->db->prepared_query('
                SELECT i.Title, h.Price, h.PurchaseDate, h.OtherUserID
                FROM bonus_history h
                INNER JOIN bonus_item i ON (i.ID = h.ItemID)
                WHERE h.UserID = ?
                ORDER BY PurchaseDate DESC
                LIMIT ? OFFSET ?
                ', $userId, $itemsPerPage, $itemsPerPage * ($page-1)
            );
            $history = $this->db->has_results() ? $this->db->to_array() : null;
            $this->cache->cache_value($key, $history, 86400 * 3);
            /* since we had to fetch this page, invalidate the next one */
            $this->cache->delete_value(self::CACHE_HISTORY . "{$userId}." . ($page+1));
        }
        return $history;
    }

    public function getUserPoolHistory($userId) {
        $key = self::CACHE_POOL_HISTORY . $userId;
        $history = $this->cache->get_value($key);
        if ($history === false) {
            $this->db->prepared_query('
                SELECT sum(c.amountrecv) AS Total, p.UntilDate, p.Name
                FROM bonus_pool_contrib c
                INNER JOIN bonus_pool p ON (p.ID = c.BonusPoolID)
                WHERE c.UserID = ?
                GROUP BY p.UntilDate, p.Name
                ORDER BY p.UntilDate, p.Name
                ', $userId
            );
            $history = $this->db->has_results() ? $this->db->to_array() : null;
            $this->cache->cache_value($key, $history, 86400 * 3);
        }
        return $history;
    }

    public function purchaseInvite($userId) {
        $item = $this->items['invite'];
        if (!\Users::canPurchaseInvite($userId, $item['MinClass'])) {
            return false;
        }
        $this->db->begin_transaction();
        if (!$this->removePoints($userId, $price)) {
            $this->db->rollback();
            return false;
        }
        $this->db->prepared_query(
            "UPDATE users_main SET Invites = Invites + 1 WHERE AND ID = ?",
            $user_id
        );
        $this->addPurchaseHistory($item['ID'], $userId, $item['Price']);
        $this->db->commit();
        $this->cache->delete_value('user_stats_' . $userId);
        $this->cache->delete_value('user_info_heavy_' . $userId);
        return true;
    }

    public function purchaseTitle($userId, $label, $title, $effectiveClass) {
        $item  = $this->items[$label];
        $title = $label === 'title-bb-y' ? \Text::full_format($title) : \Text::strip_bbcode($title);
        $price = $this->getEffectivePrice($label, $effectiveClass);

        $this->db->begin_transaction();
        /* if the price is 0, nothing changes so avoid hitting the db */
        if ($price > 0) {
            if (!$this->removePoints($userId, $price)) {
                $this->db->rollback();
                return false;
            }
        }
        if (!\Users::setCustomTitle($userId, $title)) {
            $this->db->rollback();
            return false;
        }
        $this->addPurchaseHistory($item['ID'], $userId, $price);
        $this->db->commit();
        $this->cache->delete_value('user_info_heavy_' . $userId);
        return true;
    }

    public function purchaseToken($userId, $label) {
        if (!array_key_exists($label, $this->items)) {
            return false;
        }
        $item   = $this->items[$label];
        $amount = $item['Amount'];
        $price  = $item['Price'];
        $this->db->begin_transaction();
        if (!$this->removePoints($userId, $price)) {
            $this->db->rollback();
            return false;
        }
        $this->db->prepared_query(
            'UPDATE users_main SET FLTokens = FLTokens + ? WHERE ID = ?',
            $amount, $user_id
        );

        $this->db->commit();
        $this->addPurchaseHistory($item['ID'], $userId, $price);
        $this->cache->delete_value('user_info_heavy_' . $userId);
        return true;
    }

    public function purchaseTokenOther($fromID, $toID, $label, &$logged_user) {
        if ($fromID === $toID) {
            return 0;
        }
        if (!array_key_exists($label, $this->items)) {
            return 0;
        }
        $item  = $this->items[$label];
        $amount = $item['Amount'];
        $price  = $item['Price'];
        if (!isset($price) and !($price > 0)) {
            return 0;
        }
        $From = \Users::user_info($fromID);
        $To = \Users::user_info($toID);
        if ($From['Enabled'] != 1 || $To['Enabled'] != 1) {
            return 0;
        }
        $AcceptFL = \Users::user_heavy_info($toID)['AcceptFL'];
        if (!$AcceptFL) {
            return 0;
        }

        // Burn the bonus points of the giver from the database and then update the receiver
        $this->db->begin_transaction();
        if (!$this->removePoints($fromID, $price)) {
            $this->db->rollback();
            return 0;
        }
        $this->db->prepared_query("UPDATE users_main SET FLTokens = FLTokens + ? WHERE ID=?", $amount, $toID);
        if ($this->db->affected_rows() != 1) {
            $this->db->rollback();
            return 0;
        }
        $this->addPurchaseHistory($item['ID'], $fromID, $price, $toID);
        $this->db->commit();

        $this->cache->delete_value("user_info_heavy_{$fromID}");
        $this->cache->delete_value("user_info_heavy_{$toID}");
        // the calling code may not know this has been invalidated, so we cheat
        $stats = \Users::user_stats($fromID, true);
        $logged_user['BonusPoints'] = $stats['BonusPoints'];
        self::sendPmToOther($From['Username'], $toID, $amount);

        return $amount;
    }

    public function sendPmToOther($from, $toID, $amount) {
        if ($amount > 1) {
            $is_are = 'are';
            $s = 's';
        }
        else {
            $is_are = 'is';
            $s = '';
        }
        $to = \Users::user_info($toID);
        $Body = "Hello {$to['Username']},

{$from} has sent you {$amount} freeleech token{$s} for you to use! " .
"You can use them to download torrents without getting charged any download. " .
"More details about them can be found on " .
"[url=".site_url()."wiki.php?action=article&id=57]the wiki[/url].

Enjoy!";
        \Misc::send_pm($toID, 0, "Here {$is_are} {$amount} freeleech token{$s}!", trim($Body));
    }

    private function addPurchaseHistory($item_id, $userId, $price, $other_userId = null) {
        $this->cache->delete_value(self::CACHE_SUMMARY . $userId);
        $this->cache->delete_value(self::CACHE_HISTORY . $userId . ".1");
        $this->db->prepared_query(
            'INSERT INTO bonus_history (ItemID, UserID, price, OtherUserID) VALUES (?, ?, ?, ?)',
            $item_id, $userId, $price, $other_userId
        );
        return $this->db->affected_rows();
    }

    public function setPoints($userId, $points) {
        $this->db->prepared_query('UPDATE users_main SET BonusPoints = ? WHERE ID = ?', $points, $userId);
        $this->db->prepared_query('UPDATE user_bonus SET points = ? WHERE user_id = ?', $points, $userId);
        $this->cache->delete_value("user_info_heavy_{$userId}");
        $this->cache->delete_value("user_stats_{$userId}");
    }

    public function addPoints($userId, $points) {
        $this->db->prepared_query('UPDATE users_main SET BonusPoints = BonusPoints + ? WHERE ID = ?', $points, $userId);
        $this->db->prepared_query('UPDATE user_bonus SET points = points + ? WHERE user_id = ?', $points, $userId);
        $this->cache->delete_value("user_info_heavy_{$userId}");
        $this->cache->delete_value("user_stats_{$userId}");
    }

    public function addGlobalPoints($points) {
        $this->db->prepared_query("
            INSERT INTO user_bonus
            SELECT um.ID, ?
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
            LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = 'no-fl-gifts')
            WHERE ui.DisablePoints = '0'
                AND um.Enabled = '1'
                AND uhafl.UserID IS NULL
            ON DUPLICATE KEY UPDATE points = points + ?
            ", $points, $points
        );

        $this->db->prepared_query("
            SELECT concat('user_stats_', um.ID) as ck
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
            LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = 'no-fl-gifts')
            WHERE ui.DisablePoints = '0'
                AND um.Enabled = '1'
                AND uhafl.UserID IS NULL
        ");
        if ($this->db->has_results()) {
            $keys = $this->db->collect('ck', false);
            $this->cache->deleteMulti($keys);
            return count($keys);
        }
        return 0;
    }

    public function removePointsForUpload($userId, array $torrentDetails) {
        list($Format, $Media, $Encoding, $HasLogDB, $LogScore, $LogChecksum) = $torrentDetails;
        $value = $this->getTorrentValue($Format, $Media, $Encoding, $HasLogDB, $LogScore, $LogChecksum);
        return $this->removePoints($userId, $value, true);
    }

    public function removePoints($userId, $points, $force = false) {
        if ($force) {
        // allow points to go negative
            $this->db->prepared_query('
                UPDATE users_main SET BonusPoints = BonusPoints - ?  WHERE ID = ?
                ', $points, $userId
            );
            $this->db->prepared_query('
                UPDATE user_bonus SET points = points - ?  WHERE user_id = ?
                ', $points, $userId
            );
        } else {
            // Fail if points would go negative
            $this->db->prepared_query('
                UPDATE users_main SET BonusPoints = BonusPoints - ?  WHERE BonusPoints >= ?  AND ID = ?
                ', $points, $points, $userId
            );
            if ($this->db->affected_rows() != 1) {
                return false;
            }
            $this->db->prepared_query('
                UPDATE user_bonus SET points = points - ?  WHERE points >= ?  AND user_id = ?
                ', $points, $points, $userId
            );
            if ($this->db->affected_rows() != 1) {
                return false;
            }
        }
        $this->cache->delete_value("user_info_heavy_{$userId}");
        $this->cache->delete_value("user_stats_{$userId}");
        return true;
    }

    public function userHourlyRate($userId) {
        $this->db->prepared_query('
            SELECT coalesce(sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders)), 0) as Rate
            FROM (SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE
                xfu.uid = ?
                ', $userId, $userId
        );
        list($rate) = $this->db->next_record(MYSQLI_NUM);
        return $rate;
    }

    public function userTotals($userId) {
        $this->db->prepared_query("
            SELECT
                COUNT(xfu.uid) as TotalTorrents,
                SUM(t.Size) as TotalSize,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders)), 0) AS TotalHourlyPoints
            FROM (
                SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE
                xfu.uid = ?
            ", $userId, $userId
        );
        list($total, $size, $hourly) = $this->db->next_record();
        return [intval($total), floatval($size), floatval($hourly)];
    }

    public function userDetails($userId, $orderBy, $orderWay, $limit, $offset) {
        $this->db->prepared_query("
            SELECT
                t.ID,
                t.GroupID,
                t.Size,
                t.Format,
                t.Encoding,
                t.HasLog,
                t.HasLogDB,
                t.HasCue,
                t.LogScore,
                t.LogChecksum,
                t.Media,
                t.Scene,
                t.RemasterYear,
                t.RemasterTitle,
                GREATEST(tls.Seeders, 1) AS Seeders,
                xfh.seedtime AS Seedtime,
                bonus_accrual(t.Size, xfh.seedtime, tls.Seeders) AS HourlyPoints
            FROM (
                SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE
                xfu.uid = ?
            ORDER BY $orderBy $orderWay
            LIMIT ?
            OFFSET ?
            ", $userId, $userId, $limit, $offset
        );
        return [$this->db->collect('GroupID'), $this->db->to_array('ID', MYSQLI_ASSOC)];
    }

    public function givePoints() {
        //------------------------ Update Bonus Points -------------------------//
        // calcuation:
        // Size * (0.0754 + (0.1207 * ln(1 + seedtime)/ (seeders ^ 0.55)))
        // Size (convert from bytes to GB) is in torrents
        // Seedtime (convert from hours to days) is in xbt_snatched
        // Seeders is in torrents

        $userId = 1;
        $chunk = 200;
        $processed = 0;
        $more = true;
        while ($more) {
            /* update a block of users at a time, to minimize locking contention */
            $this->db->prepared_query("
                UPDATE users_main AS um
                INNER JOIN (
                    SELECT
                        xfu.uid AS ID,
                        sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders)) as new
                    FROM (
                        SELECT DISTINCT uid, fid
                        FROM xbt_files_users
                        WHERE active = '1'
                            AND remaining = 0
                            AND mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                            AND uid BETWEEN ? AND ?
                    ) xfu
                    INNER JOIN xbt_files_history AS xfh USING (uid, fid)
                    INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                    INNER JOIN users_info AS ui ON (ui.UserID = xfu.uid)
                    INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                    INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                    WHERE ui.DisablePoints = '0'
                        AND um.Enabled = '1'
                        AND um.ID BETWEEN ? AND ?
                    GROUP BY
                        xfu.uid
                ) AS p USING (ID)
                SET um.BonusPoints = um.BonusPoints + p.new
                ", $userId, $userId + $chunk - 1, $userId, $userId + $chunk - 1
            );
            $processed += $this->db->affected_rows();

            $this->db->prepared_query("
                INSERT INTO user_bonus
                SELECT
                    xfu.uid AS ID,
                    sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders)) as new
                FROM (
                    SELECT DISTINCT uid, fid
                    FROM xbt_files_users
                    WHERE active = '1'
                        AND remaining = 0
                        AND mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                        AND uid BETWEEN ? AND ?
                ) xfu
                INNER JOIN xbt_files_history AS xfh USING (uid, fid)
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN users_info AS ui ON (ui.UserID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                WHERE ui.DisablePoints = '0'
                    AND um.Enabled = '1'
                    AND um.ID BETWEEN ? AND ?
                GROUP BY
                    xfu.uid
                ON DUPLICATE KEY UPDATE points = points + VALUES(points)
                ", $userId, $userId + $chunk - 1, $userId, $userId + $chunk - 1
            );

            /* flush their stats */
            $this->db->prepared_query("
                SELECT concat('user_stats_', um.ID) as ck
                FROM users_main um
                INNER JOIN users_info ui ON (ui.UserID = um.ID)
                WHERE ui.DisablePoints = '0'
                    AND um.Enabled = '1'
                    AND um.ID BETWEEN ? AND ?
                ", $userId, $userId + $chunk - 1
            );
            if ($this->db->has_results()) {
                $this->cache->deleteMulti($this->db->collect('ck', false));
            }
            $userId += $chunk;

            /* see if there are some more users to process */
            $this->db->prepared_query('
                SELECT 1
                FROM users_main
                WHERE ID >= ?
                ', $userId
            );
            $more = $this->db->has_results();
        }
        return $processed;
    }
}
