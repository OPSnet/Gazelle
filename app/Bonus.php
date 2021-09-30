<?php

namespace Gazelle;

use \Gazelle\Exception\BonusException;

class Bonus extends Base {
    const CACHE_OPEN_POOL = 'bonus_pool'; // also defined in Manager\Bonus
    const CACHE_PURCHASE = 'bonus_purchase_%d';
    const CACHE_SUMMARY = 'bonus_summary_%d';
    const CACHE_HISTORY = 'bonus_history_%d_%d';
    const CACHE_POOL_HISTORY = 'bonus_pool_history_%d';

    protected User $user;
    protected int $userId;

    public function __construct(User $user) {
        parent::__construct();
        $this->user = $user;
        $this->userId = $user->id();
    }

    public function tableName(): string {
        return 'bonus_history';
    }

    public function flush() {
        $this->cache->deleteMulti([
            'u_' . $this->user->id(),
            'user_info_heavy_' . $this->user->id(),
            'user_stats_' . $this->user->id(),
            sprintf(self::CACHE_HISTORY, $this->userId, 0),
            sprintf(self::CACHE_POOL_HISTORY, $this->userId),
        ]);
    }

    protected function items(): array {
        return (new Manager\Bonus)->itemList();
    }

    public function getListForUser(): array {
        $items = $this->items();
        $allowed = [];
        foreach ($items as $item) {
            if ($item['Label'] === 'seedbox' && $this->user->hasAttr('feature-seedbox')) {
                continue;
            } elseif (
                $item['Label'] === 'invite'
                && (check_perms('site_send_unlimited_invites') || !$this->user->canPurchaseInvite())
            ) {
                continue;
            }
            $item['Price'] = $this->getEffectivePrice($item['Label']);
            $allowed[] = $item;
        }
        return $allowed;
    }

    public function getItem($label): ?array {
        $items = $this->items();
        return array_key_exists($label, $items) ? $items[$label] : null;
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
        elseif ($format == 'MP3' && in_array($encoding, ['V0 (VBR)', '320'])) {
            return BONUS_AWARD_MP3;
        }
        return BONUS_AWARD_OTHER;
    }

    public function getEffectivePrice($label): int {
        $item = $this->items()[$label];
        if (preg_match('/^collage-\d$/', $label)) {
            return $item['Price'] * pow(2, $this->user->paidPersonalCollages());
        }
        return $this->user->effectiveClass() >= $item['FreeClass'] ? 0 : (int)$item['Price'];
    }

    public function getListOther($balance): array {
        $items = $this->items();
        $other = [];
        foreach ($items as $label => $item) {
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

    public function summary() {
        $key = sprintf(self::CACHE_SUMMARY, $this->userId);
        $summary = $this->cache->get_value($key);
        if ($summary === false) {
            $summary = $this->db->rowAssoc('
                SELECT count(*) AS nr,
                    coalesce(sum(price), 0) AS total
                FROM bonus_history
                WHERE UserID = ?
                ', $this->userId
            );
            $this->cache->cache_value($key, $summary, 86400 * 7);
        }
        return $summary;
    }

    public function history(int $limit, int $offset): array {
        $page = $offset / $limit;
        $key = sprintf(self::CACHE_HISTORY, $this->userId, $page);
        $history = $this->cache->get_value($key);
        if ($history === false) {
            $this->db->prepared_query('
                SELECT i.Title, h.Price, h.PurchaseDate, h.OtherUserID
                FROM bonus_history h
                INNER JOIN bonus_item i ON (i.ID = h.ItemID)
                WHERE h.UserID = ?
                ORDER BY PurchaseDate DESC
                LIMIT ? OFFSET ?
                ', $this->userId, $limit, $offset
            );
            $history = $this->db->to_array();
            $this->cache->cache_value($key, $history, 86400 * 3);
            /* since we had to fetch this page, invalidate the next one */
            $this->cache->delete_value(sprintf(self::CACHE_HISTORY, $this->userId, $page+1));
        }
        return $history;
    }

    public function donate(int $poolId, int $value) {
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
        (new \Gazelle\BonusPool($poolId))->contribute($this->userId, $value, $taxedValue);

        $this->cache->delete(self::CACHE_OPEN_POOL);
        return true;
    }

    public function poolHistory(): array {
        $key = sprintf(self::CACHE_POOL_HISTORY, $this->userId);
        $history = $this->cache->get_value($key);
        if ($history === false) {
            $this->db->prepared_query('
                SELECT sum(c.amount_recv) AS total, p.until_date, p.name
                FROM bonus_pool_contrib c
                INNER JOIN bonus_pool p USING (bonus_pool_id)
                WHERE c.user_id = ?
                GROUP BY p.until_date, p.name
                ORDER BY p.until_date, p.name
                ', $this->userId
            );
            $history = $this->db->to_array();
            $this->cache->cache_value($key, $history, 86400 * 3);
        }
        return $history;
    }

    /**
     * Get the total purchases of all items by a user
     *
     * @return array of [title, total]
     */
    public function purchaseHistoryByUser(): array {
        $key = sprintf(self::CACHE_PURCHASE, $this->userId);
        $history = $this->cache->get_value($key);
        if ($history === false) {
            $this->db->prepared_query("
                SELECT bi.ID as id,
                    bi.Title AS title,
                    count(bh.ID) AS total,
                    sum(bh.Price) AS cost
                FROM bonus_item bi
                LEFT JOIN bonus_history bh ON (bh.ItemID = bi.ID)
                WHERE bh.UserID = ?
                GROUP BY bi.Title
                ORDER BY bi.sequence
                ", $this->userId
            );
            $history = $this->db->to_array('id', MYSQLI_ASSOC, false);
            $this->cache->cache_value($key, $history, 86400 * 3);
        }
        return $history;
    }

    public function purchaseInvite() {
        if (!$this->user->canPurchaseInvite()) {
            throw new BonusException('invite:minclass');
        }
        $item = $this->items()['invite'];
        $price = $item['Price'];
        $this->db->prepared_query('
            UPDATE user_bonus ub
            INNER JOIN users_main um ON (um.ID = ub.user_id) SET
                ub.points = ub.points - ?,
                um.Invites = um.Invites + 1
            WHERE ub.points >= ?
                AND ub.user_id = ?
            ', $price, $price, $this->userId
        );
        if ($this->db->affected_rows() != 2) {
            throw new BonusException('invite:nofunds');
        }
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function purchaseTitle($label, $title) {
        $item  = $this->items()[$label];
        $title = $label === 'title-bb-y' ? \Text::full_format($title) : \Text::strip_bbcode($title);

        try {
            $this->user->setTitle($title);
        } catch (Exception\UserException $e) {
            throw new BonusException('title:too-long');
        }

        /* if the price is 0, nothing changes so avoid hitting the db */
        $price = $this->getEffectivePrice($label);
        if ($price > 0) {
            if (!$this->removePoints($price)) {
                throw new BonusException('title:nofunds');
            }
        }

        $this->user->modify();
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function purchaseCollage($label) {
        $item  = $this->items()[$label];
        $price = $this->getEffectivePrice($label);
        $this->db->prepared_query('
            UPDATE user_bonus ub
            INNER JOIN users_info ui ON (ui.UserID = ub.user_id) SET
                ub.points = ub.points - ?,
                ui.collages = ui.collages + 1
            WHERE ub.points >= ?
                AND ub.user_id = ?
            ', $price, $price, $this->userId
        );
        $rows = $this->db->affected_rows();
        if (!(($price > 0 && $rows === 2) || ($price === 0 && $rows === 1))) {
            throw new BonusException('collage:nofunds');
        }
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function unlockSeedbox() {
        $item  = $this->items()['seedbox'];
        $price = $this->getEffectivePrice('seedbox');
        $this->db->begin_transaction();
        $this->db->prepared_query('
            UPDATE user_bonus ub SET
                ub.points = ub.points - ?
            WHERE ub.points >= ?
                AND ub.user_id = ?
            ', $price, $price, $this->userId
        );
        if ($this->db->affected_rows() != 1) {
            $this->db->rollback();
            throw new BonusException('seedbox:nofunds');
        }
        try {
            $this->db->prepared_query("
                INSERT INTO user_has_attr
                       (UserID, UserAttrID)
                VALUES (?,      (SELECT ID FROM user_attr WHERE Name = ?))
                ", $this->userId, 'feature-seedbox'
            );
        } catch (\DB_MYSQL_DuplicateKeyException $e) {
            // no point in buying a second time
            $this->db->rollback();
            throw new BonusException('seedbox:already-purchased');
        }
        $this->db->commit();
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return true;
    }

    public function purchaseToken($label): int {
        $item = $this->items()[$label];
        if (!$item) {
            throw new BonusException('selfToken:badlabel');
        }
        $amount = (int)$item['Amount'];
        $price  = $item['Price'];
        $this->db->prepared_query('
            UPDATE user_bonus ub
            INNER JOIN user_flt uf USING (user_id) SET
                ub.points = ub.points - ?,
                uf.tokens = uf.tokens + ?
            WHERE ub.user_id = ?
                AND ub.points >= ?
            ', $price, $amount, $this->userId, $price
        );
        if ($this->db->affected_rows() != 2) {
            throw new BonusException('selfToken:funds');
        }
        $this->addPurchaseHistory($item['ID'], $price);
        $this->flush();
        return $amount;
    }

    public function purchaseTokenOther($toID, $label) {
        if ($this->userId === $toID) {
            throw new BonusException('otherToken:self');
        }
        $item = $this->items()[$label];
        if (!$item) {
            throw new BonusException('otherToken:badlabel');
        }
        $amount = (int)$item['Amount'];
        $price  = $item['Price'];

        /* Take the bonus points from the giver and give tokens
         * to the receiver, unless the latter have asked to
         * refuse receiving tokens.
         */
        $this->db->prepared_query("
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
            ", $price, $amount, $toID, $this->userId, $price
        );
        if ($this->db->affected_rows() != 2) {
            throw new BonusException('otherToken:no-gift-funds');
        }
        $this->addPurchaseHistory($item['ID'], $price, $toID);
        $this->cache->deleteMulti([
            'u_' . $this->userId,
            'u_' . $toID,
            'user_info_heavy_' . $this->userId,
            'user_info_heavy_' . $toID,
            'user_stats_' . $this->userId,
            'user_stats_' . $toID,
        ]);
        $this->sendPmToOther($toID, $amount);

        return $amount;
    }

    public function sendPmToOther($toID, $amount) {
        (new Manager\User)->sendPM($toID, 0,
            "Here " . ($amount == 1 ? 'is' : 'are') . ' ' . article($amount) . " freeleech token" . plural($amount) . "!",
            $this->twig->render('bonus/token-other.twig', [
                'TO'       => (new User($toID))->username(),
                'FROM'     => $this->user->username(),
                'AMOUNT'   => $amount,
                'PLURAL'   => plural($amount),
                'WIKI_ID'  => 57,
            ])
        );
    }

    private function addPurchaseHistory($itemId, $price, $otherUserId = null): int {
        $this->cache->deleteMulti([
            sprintf(self::CACHE_PURCHASE, $this->userId),
            sprintf(self::CACHE_SUMMARY, $this->userId),
            sprintf(self::CACHE_HISTORY, $this->userId, 0)
        ]);
        $this->db->prepared_query("
            INSERT INTO bonus_history
                   (ItemID, UserID, Price, OtherUserID)
            VALUES (?,      ?,      ?,     ?)
            ", $itemId, $this->userId, $price, $otherUserId
        );
        return $this->db->affected_rows();
    }

    public function setPoints(int $points): int {
        $this->db->prepared_query("
            UPDATE user_bonus SET
                points = ?
            WHERE user_id = ?
            ", $points, $this->userId
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function addPoints(int $points): int {
        $this->db->prepared_query("
            UPDATE user_bonus SET
                points = points + ?
            WHERE user_id = ?
            ", $points, $this->userId
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function removePointsForUpload(array $torrentDetails): int {
        [$Format, $Media, $Encoding, $HasLogDB, $LogScore, $LogChecksum] = $torrentDetails;
        $value = $this->getTorrentValue($Format, $Media, $Encoding, $HasLogDB, $LogScore, $LogChecksum);
        return $this->removePoints($value, true);
    }

    public function removePoints($points, $force = false): bool {
        if ($force) {
            // allow points to go negative
            $this->db->prepared_query('
                UPDATE user_bonus SET points = points - ? WHERE user_id = ?
                ', $points, $this->userId
            );
        } else {
            // Fail if points would go negative
            $this->db->prepared_query('
                UPDATE user_bonus SET points = points - ? WHERE points >= ?  AND user_id = ?
                ', $points, $points, $this->userId
            );
            if ($this->db->affected_rows() != 1) {
                return false;
            }
        }
        $this->flush();
        return true;
    }

    public function hourlyRate(): float {
        return $this->db->scalar("
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
            ", $this->userId, $this->userId
        ) ?? 0.0;
    }

    public function userTotals() {
        [$total, $size, $hourly, $daily, $weekly, $monthly, $yearly, $ppGB] = $this->db->row("
            SELECT
                count(xfu.uid) as TotalTorrents,
                sum(t.Size) as TotalSize,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime,                           tls.Seeders)), 0)                           AS HourlyPoints,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 1),                tls.Seeders)), 0) * (24 * 1)                AS DailyPoints,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 7),                tls.Seeders)), 0) * (24 * 7)                AS WeeklyPoints,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004/12), tls.Seeders)), 0) * (24 * 365.256363004/12) AS MonthlyPoints,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders)), 0) * (24 * 365.256363004)    AS YearlyPoints,
                coalesce(sum(bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders)), 0) * (24 * 365.256363004)
                    / (coalesce(sum(t.Size), 1) / (1024*1024*1024)) AS PointsPerGB
            FROM (
                SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE
                xfu.uid = ?
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
            ", $this->userId, $this->userId
        );
        return [(int)$total, (float)$size, (float)$hourly, (float)$daily, (float)$weekly, (float)$monthly, (float)$yearly, (float)$ppGB];
    }

    public function userDetails($orderBy, $orderWay, $limit, $offset) {
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
                bonus_accrual(t.Size, xfh.seedtime,                           tls.Seeders)                           AS HourlyPoints,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 1),                tls.Seeders) * (24 * 1)                AS DailyPoints,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 7),                tls.Seeders) * (24 * 7)                AS WeeklyPoints,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004/12), tls.Seeders) * (24 * 365.256363004/12) AS MonthlyPoints,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders) * (24 * 365.256363004)    AS YearlyPoints,
                bonus_accrual(t.Size, xfh.seedtime + (24 * 365.256363004),    tls.Seeders) * (24 * 365.256363004)
                    / (t.Size / (1024*1024*1024)) AS PointsPerGB
            FROM (
                SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
            ) AS xfu
            INNER JOIN xbt_files_history AS xfh USING (uid, fid)
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE
                xfu.uid = ?
                AND NOT (t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)')
            ORDER BY $orderBy $orderWay
            LIMIT ?
            OFFSET ?
            ", $this->userId, $this->userId, $limit, $offset
        );
        return [$this->db->collect('GroupID'), $this->db->to_array('ID', MYSQLI_ASSOC)];
    }
}
