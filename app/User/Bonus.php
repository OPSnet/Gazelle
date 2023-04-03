<?php

namespace Gazelle\User;

class Bonus extends \Gazelle\BaseUser {
    final const CACHE_PURCHASE     = 'bonus_purchase_%d';
    final const CACHE_SUMMARY      = 'bonus_summary_%d';
    final const CACHE_HISTORY      = 'bonus_history_%d_%d';
    final const CACHE_POOL_HISTORY = 'bonus_pool_history_%d';

    public function flush(): Bonus {
        $this->user->flush();
        self::$cache->delete_multi([
            sprintf(self::CACHE_HISTORY, $this->user->id(), 0),
            sprintf(self::CACHE_POOL_HISTORY, $this->user->id()),
        ]);
        return $this;
    }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }
    public function tableName(): string { return 'bonus_history'; }

    public function pointsSpent(): int {
        return (int)self::$db->scalar("
            SELECT sum(Price) FROM bonus_history WHERE UserID = ?
            ", $this->user->id()
        );
    }

    protected function items(): array {
        return (new \Gazelle\Manager\Bonus)->itemList();
    }

    public function itemList(): array {
        $items = $this->items();
        $allowed = [];
        foreach ($items as $item) {
            if ($item['Label'] === 'seedbox' && $this->user->hasAttr('feature-seedbox')) {
                continue;
            } elseif (
                $item['Label'] === 'invite'
                && ($this->user->permitted('site_send_unlimited_invites') || !$this->user->canPurchaseInvite())
            ) {
                continue;
            }
            $item['Price'] = $this->effectivePrice($item['Label']);
            $allowed[] = $item;
        }
        return $allowed;
    }

    public function item(string $label): ?array {
        $items = $this->items();
        return $items[$label] ?? null;
    }

    public function torrentValue(\Gazelle\Torrent $torrent): int {
        if ($torrent->format() == 'FLAC') {
            if ($torrent->isPerfectFlac()) {
                return BONUS_AWARD_FLAC_PERFECT;
            } else {
                return BONUS_AWARD_FLAC;
            }
        } elseif ($torrent->format() == 'MP3' && in_array($torrent->encoding(), ['V0 (VBR)', '320'])) {
            return BONUS_AWARD_MP3;
        }
        return BONUS_AWARD_OTHER;
    }

    public function effectivePrice(string $label): int {
        $item = $this->items()[$label];
        if (preg_match('/^collage-\d$/', $label)) {
            return $item['Price'] * 2 ** $this->user->paidPersonalCollages();
        }
        return $this->user->effectiveClass() >= $item['FreeClass'] ? 0 : (int)$item['Price'];
    }

    public function otherList(): array {
        $balance = $this->user->bonusPointsTotal();
        $other   = [];
        foreach ($this->items() as $label => $item) {
            if (preg_match('/^other-\d$/', $label) && $balance >= $item['Price']) {
                $other[] = [
                    'Label' => $item['Label'],
                    'Name'  => $item['Title'],
                    'Price' => $item['Price'],
                    'After' => $balance - $item['Price'],
                ];
            }
        }
        return $other;
    }

    public function otherLatest(\Gazelle\User $other): array {
        return self::$db->rowAssoc("
            SELECT bi.Title     AS title,
                bh.PurchaseDate AS purchase_date
            FROM bonus_history bh
            INNER JOIN bonus_item bi ON (bi.ID = bh.ItemID)
            WHERE bh.UserID = ?
                AND bh.OtherUserID = ?
            ORDER BY bh.PurchaseDate DESC
            LIMIT 1
            ", $this->user->id(), $other->id()
        ) ?? [];
    }

    public function summary(): array {
        $key = sprintf(self::CACHE_SUMMARY, $this->user->id());
        $summary = self::$cache->get_value($key);
        if ($summary === false) {
            $summary = self::$db->rowAssoc('
                SELECT count(*) AS nr,
                    coalesce(sum(price), 0) AS total
                FROM bonus_history
                WHERE UserID = ?
                ', $this->user->id()
            );
            self::$cache->cache_value($key, $summary, 86400 * 7);
        }
        return $summary;
    }

    public function history(int $limit, int $offset): array {
        $page = $offset / $limit;
        $key = sprintf(self::CACHE_HISTORY, $this->user->id(), $page);
        $history = self::$cache->get_value($key);
        if ($history === false) {
            self::$db->prepared_query('
                SELECT i.Title, h.Price, h.PurchaseDate, h.OtherUserID
                FROM bonus_history h
                INNER JOIN bonus_item i ON (i.ID = h.ItemID)
                WHERE h.UserID = ?
                ORDER BY PurchaseDate DESC
                LIMIT ? OFFSET ?
                ', $this->user->id(), $limit, $offset
            );
            $history = self::$db->to_array();
            self::$cache->cache_value($key, $history, 86400 * 3);
            /* since we had to fetch this page, invalidate the next one */
            self::$cache->delete_value(sprintf(self::CACHE_HISTORY, $this->user->id(), $page+1));
        }
        return $history;
    }

    public function donate(int $poolId, int $value): bool {
        $effectiveClass = $this->user->effectiveClass();
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

        if (!$this->removePoints($value)) {
            return false;
        }
        $this->user->flush();
        (new \Gazelle\BonusPool($poolId))->contribute($this->user->id(), $value, $taxedValue);
        return true;
    }

    public function poolHistory(): array {
        $key = sprintf(self::CACHE_POOL_HISTORY, $this->user->id());
        $history = self::$cache->get_value($key);
        if ($history === false) {
            self::$db->prepared_query('
                SELECT sum(c.amount_recv) AS total, p.until_date, p.name
                FROM bonus_pool_contrib c
                INNER JOIN bonus_pool p USING (bonus_pool_id)
                WHERE c.user_id = ?
                GROUP BY p.until_date, p.name
                ORDER BY p.until_date, p.name
                ', $this->user->id()
            );
            $history = self::$db->to_array();
            self::$cache->cache_value($key, $history, 86400 * 3);
        }
        return $history;
    }

    /**
     * Get the total purchases of all their items
     *
     * @return array of [title, total]
     */
    public function purchaseHistory(): array {
        $key = sprintf(self::CACHE_PURCHASE, $this->user->id());
        $history = self::$cache->get_value($key);
        if ($history === false) {
            self::$db->prepared_query("
                SELECT bi.ID as id,
                    bi.Title AS title,
                    count(bh.ID) AS total,
                    sum(bh.Price) AS cost
                FROM bonus_item bi
                LEFT JOIN bonus_history bh ON (bh.ItemID = bi.ID)
                WHERE bh.UserID = ?
                GROUP BY bi.Title
                ORDER BY bi.sequence
                ", $this->user->id()
            );
            $history = self::$db->to_array('id', MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $history, 86400 * 3);
        }
        return $history;
    }

    public function purchaseInvite(): bool {
        if (!$this->user->canPurchaseInvite()) {
            return false;
        }
        $item = $this->items()['invite'];
        $price = $item['Price'];
        self::$db->prepared_query('
            UPDATE user_bonus ub
            INNER JOIN users_main um ON (um.ID = ub.user_id) SET
                ub.points = ub.points - ?,
                um.Invites = um.Invites + 1
            WHERE ub.points >= ?
                AND ub.user_id = ?
            ', $price, $price, $this->user->id()
        );
        if (self::$db->affected_rows() != 2) {
            return false;
        }
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function purchaseTitle(string $label, string $title): bool {
        $item  = $this->items()[$label];
        $title = $label === 'title-bb-y' ? \Text::full_format($title) : \Text::strip_bbcode($title);

        if (!$this->user->setTitle($title)) {
            return false;
        }

        /* if the price is 0, nothing changes so avoid hitting the db */
        $price = $this->effectivePrice($label);
        if ($price > 0) {
            if (!$this->removePoints($price)) {
                return false;
            }
        }

        $this->user->modify();
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function purchaseCollage(string $label): bool {
        $item  = $this->items()[$label];
        $price = $this->effectivePrice($label);
        self::$db->prepared_query('
            UPDATE user_bonus ub
            INNER JOIN users_info ui ON (ui.UserID = ub.user_id) SET
                ub.points = ub.points - ?,
                ui.collages = ui.collages + 1
            WHERE ub.points >= ?
                AND ub.user_id = ?
            ', $price, $price, $this->user->id()
        );
        $rows = self::$db->affected_rows();
        if (($price > 0 && $rows !== 2) || ($price === 0 && $rows !== 1)) {
            return false;
        }
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function unlockSeedbox(): bool {
        $item  = $this->items()['seedbox'];
        $price = $this->effectivePrice('seedbox');
        self::$db->begin_transaction();
        self::$db->prepared_query('
            UPDATE user_bonus ub SET
                ub.points = ub.points - ?
            WHERE ub.points >= ?
                AND ub.user_id = ?
            ', $price, $price, $this->user->id()
        );
        if (self::$db->affected_rows() != 1) {
            self::$db->rollback();
            return false;
        }
        try {
            self::$db->prepared_query("
                INSERT INTO user_has_attr
                       (UserID, UserAttrID)
                VALUES (?,      (SELECT ID FROM user_attr WHERE Name = ?))
                ", $this->user->id(), 'feature-seedbox'
            );
        } catch (\Gazelle\DB\Mysql_DuplicateKeyException) {
            // no point in buying a second time
            self::$db->rollback();
            return false;
        }
        self::$db->commit();
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function purchaseToken(string $label): bool {
        $item = $this->items()[$label];
        if (!$item) {
            return false;
        }
        $amount = (int)$item['Amount'];
        $price  = $item['Price'];
        self::$db->prepared_query('
            UPDATE user_bonus ub
            INNER JOIN user_flt uf USING (user_id) SET
                ub.points = ub.points - ?,
                uf.tokens = uf.tokens + ?
            WHERE ub.user_id = ?
                AND ub.points >= ?
            ', $price, $amount, $this->user->id(), $price
        );
        if (self::$db->affected_rows() != 2) {
            return false;
        }
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    /**
     * This method does not return a boolean success, but rather the number of
     * tokens purchased (for use in a response to the receiver).
     */
    public function purchaseTokenOther(\Gazelle\User $receiver, string $label, string $message): int {
        if ($this->user->id() === $receiver->id()) {
            return 0;
        }
        $item = $this->items()[$label];
        if (!$item) {
            return 0;
        }
        $amount = (int)$item['Amount'];
        $price  = $item['Price'];

        /* Take the bonus points from the giver and give tokens
         * to the receiver, unless the latter have asked to
         * refuse receiving tokens.
         */
        self::$db->prepared_query("
            UPDATE user_bonus ub
            INNER JOIN users_main self ON (self.ID = ub.user_id),
                users_main other
                INNER JOIN user_flt other_uf ON (other_uf.user_id = other.ID)
                LEFT JOIN user_has_attr noFL ON (noFL.UserID = other.ID AND noFL.UserAttrId
                    = (SELECT ua.ID FROM user_attr ua WHERE ua.Name = 'no-fl-gifts')
                )
            SET
                ub.points = ub.points - ?,
                other_uf.tokens = other_uf.tokens + ?
            WHERE noFL.UserID IS NULL
                AND other.Enabled = '1'
                AND other.ID = ?
                AND self.ID = ?
                AND ub.points >= ?
            ", $price, $amount, $receiver->id(), $this->user->id(), $price
        );
        if (self::$db->affected_rows() != 2) {
            return 0;
        }
        $this->addPurchaseHistory($item['ID'], $price, $receiver->id());
        $this->sendPmToOther($receiver, $amount, $message);
        $this->flush();
        $receiver->flush();

        return $amount;
    }

    public function sendPmToOther(\Gazelle\User $receiver, int $amount, string $message): int {
        return (new \Gazelle\Manager\User)->sendPM($receiver->id(), 0,
            "Here " . ($amount == 1 ? 'is' : 'are') . ' ' . article($amount) . " freeleech token" . plural($amount) . "!",
            self::$twig->render('bonus/token-other-message.twig', [
                'to'       => $receiver->username(),
                'from'     => $this->user->username(),
                'amount'   => $amount,
                'wiki_id'  => 57,
                'message'  => $message
            ])
        );
    }

    private function addPurchaseHistory(int $itemId, int $price, $otherUserId = null): int {
        self::$cache->delete_multi([
            sprintf(self::CACHE_PURCHASE, $this->user->id()),
            sprintf(self::CACHE_SUMMARY, $this->user->id()),
            sprintf(self::CACHE_HISTORY, $this->user->id(), 0)
        ]);
        self::$db->prepared_query("
            INSERT INTO bonus_history
                   (ItemID, UserID, Price, OtherUserID)
            VALUES (?,      ?,      ?,     ?)
            ", $itemId, $this->user->id(), $price, $otherUserId
        );
        return self::$db->affected_rows();
    }

    public function setPoints(float $points): int {
        self::$db->prepared_query("
            UPDATE user_bonus SET
                points = ?
            WHERE user_id = ?
            ", $points, $this->user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function addPoints(float $points): int {
        self::$db->prepared_query("
            UPDATE user_bonus SET
                points = points + ?
            WHERE user_id = ?
            ", $points, $this->user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function removePointsForUpload(\Gazelle\Torrent $torrent): bool {
        return $this->removePoints($this->torrentValue($torrent), true);
    }

    public function removePoints(float $points, bool $force = false): bool {
        if ($force) {
            // allow points to go negative
            self::$db->prepared_query('
                UPDATE user_bonus SET points = points - ? WHERE user_id = ?
                ', $points, $this->user->id()
            );
        } else {
            // Fail if points would go negative
            self::$db->prepared_query('
                UPDATE user_bonus SET points = points - ? WHERE points >= ?  AND user_id = ?
                ', $points, $points, $this->user->id()
            );
            if (self::$db->affected_rows() != 1) {
                return false;
            }
        }
        $this->flush();
        return true;
    }

    public function hourlyRate(): float {
        return (float)self::$db->scalar("
            SELECT sum(bonus_accrual(t.Size, xfh.seedtime, tls.Seeders))
            FROM (
                SELECT DISTINCT uid,fid
                FROM xbt_files_users
                WHERE active = 1 AND remaining = 0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE xfu.uid = ?
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
            ", $this->user->id(), $this->user->id()
        );
    }

    public function userTotals(): array {
        $stats = self::$db->rowAssoc("
            SELECT count(*) AS total_torrents,
                coalesce(sum(t.Size), 0)   AS total_size,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime,                           tls.Seeders)), 0)                           AS hourly_points,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 1),                tls.Seeders)), 0) * (24 * 1)                AS daily_points,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 7),                tls.Seeders)), 0) * (24 * 7)                AS weekly_points,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004/12), tls.Seeders)), 0) * (24 * 365.256363004/12) AS monthly_points,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders)), 0) * (24 * 365.256363004)    AS yearly_points,
                if (coalesce(sum(t.Size), 0) = 0,
                    0,
                    sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders)) * (24 * 365.256363004)
                    / sum(t.Size) / (1024*1024*1024)
                ) AS points_per_gb
            FROM (
                SELECT DISTINCT uid, fid
                FROM xbt_files_users
                WHERE active = 1
                    AND remaining = 0
                    AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR)
                    AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE xfu.uid = ?
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
            ", $this->user->id(), $this->user->id()
        );
        $stats['total_size'] = (int)$stats['total_size'];
        return $stats;
    }

    public function seedList(string $orderBy, string $orderWay, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT
                t.ID,
                t.Size                   AS size,
                GREATEST(tls.Seeders, 1) AS seeders,
                xfh.seedtime             AS seed_time,
                bonus_accrual(t.Size, xfh.seedtime,                           tls.Seeders)                           AS hourly_points,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 1),                tls.Seeders) * (24 * 1)                AS daily_points,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 7),                tls.Seeders) * (24 * 7)                AS weekly_points,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004/12), tls.Seeders) * (24 * 365.256363004/12) AS monthly_points,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders) * (24 * 365.256363004)    AS yearly_points,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders) * (24 * 365.256363004)
                    / (t.Size / (1024*1024*1024)) AS points_per_gb
            FROM (
                SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
            WHERE
                xfu.uid = ?
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
            ORDER BY $orderBy $orderWay
            LIMIT ?
            OFFSET ?
            ", $this->user->id(), $this->user->id(), $limit, $offset
        ); $list = [];
        $result = self::$db->to_array('ID', MYSQLI_ASSOC, false);
        foreach ($result as $r) {
            $r['torrent'] = new \Gazelle\Torrent($r['ID']);
            $list[] = $r;
        }
        return $list;
    }
}
