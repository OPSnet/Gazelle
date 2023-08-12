<?php

namespace Gazelle;

class Contest extends BaseObject {
    final const CACHE_CONTEST = 'contestv2_%d';
    final const CACHE_STATS   = 'contest_stats_%d';
    final const CONTEST_LEADERBOARD_CACHE_KEY = 'contest_leaderboard_%d_%d';

    protected array $stats; /* entries, users */

    public function flush(): Contest {
        self::$cache->delete_value(sprintf(self::CACHE_CONTEST, $this->id));
        $this->info = [];
        return $this;
    }
    public function link(): string { return "<a href=\"{$this->url()}\">{$this->name()}</a>"; }
    public function location(): string { return "contest.php?id={$this->id}"; }
    public function pkName(): string { return "contest_id"; }
    public function tableName(): string { return "contest"; }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_CONTEST, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT t.name AS contest_type, c.name, c.banner, c.description, c.display, c.date_begin, c.date_end,
                    cbp.bonus_pool_id AS bonus_pool_id,
                    cbp.status AS bonus_status,
                    cbp.bonus_user,
                    cbp.bonus_contest,
                    cbp.bonus_per_entry,
                    IF (now() BETWEEN c.date_begin AND c.date_end, 1, 0) AS is_open,
                    IF (cbp.bonus_pool_id IS NOT NULL AND cbp.status = ? AND now() > c.date_end, 1, 0) AS payout_ready
                FROM contest c
                INNER JOIN contest_type t USING (contest_type_id)
                LEFT JOIN contest_has_bonus_pool cbp USING (contest_id)
                WHERE c.contest_id = ?
                ", 'open', $this->id
            );
            self::$cache->cache_value($key, $info, 86400 * 3);
        }

        // upload-flac-no-single => UploadFlacNoSingle
        $className = '\\Gazelle\\Contest\\' . implode('', array_map('ucfirst', explode('-', $info['contest_type'])));
        $info['type'] = new $className($this->id, $info['date_begin'], $info['date_end']);

        if ($info['bonus_pool_id']) {
            $info['bonus_pool'] = new BonusPool($info['bonus_pool_id']);
            // calculate the ratios of how the bonus pool is carved up
            // sum(bonusUser + bonusContest + bonusPerEntry) == 1.0
            $bonusClaimSum = $info['bonus_user'] + $info['bonus_contest'] + $info['bonus_per_entry'];
            $info['bonus_user_ratio']      = $info['bonus_user'] / $bonusClaimSum;
            $info['bonus_contest_ratio']   = $info['bonus_contest'] / $bonusClaimSum;
            $info['bonus_per_entry_ratio'] = 1 - ($info['bonus_user_ratio'] + $info['bonus_contest_ratio']);
        } else {
            $info['bonus_pool']           = null;
            $info['bonus_user_ratio']      = 0.0;
            $info['bonus_contest_ratio']   = 0.0;
            $info['bonus_per_entry_ratio'] = 0.0;
        }
        $this->info = $info;
        return $this->info;
    }

    public function contestType(): string {
        return $this->info()['contest_type'];
    }

    public function banner(): string {
        return $this->info()['banner'];
    }

    public function dateBegin(): string {
        return $this->info()['date_begin'];
    }

    public function dateEnd(): string {
        return $this->info()['date_end'];
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function display(): int {
        return $this->info()['display'];
    }

    public function leaderboard(int $limit, int $offset): array {
        return $this->type()->leaderboard($limit, $offset); /** @phpstan-ignore-line */
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function rank(\Gazelle\User $user): ?array {
        $page   = 0;
        $userId = $user->id();
        while (true) {
            $leaderboard = $this->type()->leaderboard(CONTEST_ENTRIES_PER_PAGE, $page * CONTEST_ENTRIES_PER_PAGE);  /** @phpstan-ignore-line */
            if (!$leaderboard) {
                break;
            }
            for ($i = 0, $max = count($leaderboard); $i < $max; $i++) {
                if ($userId == $leaderboard[$i]['user_id']) {
                    return [
                        'position' => 1 + $i + $page * CONTEST_ENTRIES_PER_PAGE,
                        'total'    => $leaderboard[$i]['entry_count'],
                    ];
                }
            }
            if (++$page > 1000) {
                break; // sanity check
            }
        }
        return null;
    }

    protected function participationStats(): array {
        if (!isset($this->stats)) {
            $key = sprintf(self::CACHE_STATS, $this->id);
            $stats = self::$cache->get_value($key);
            if ($stats === false) {
                $stats = $this->type()->participationStats();
                self::$cache->cache_value($key, $stats, 900);
            }
            $this->stats = $stats;
        }
        return $this->stats;
    }

    public function totalEntries(): int {
        return $this->participationStats()['total_entries'];
    }

    public function totalUsers(): int {
        return $this->participationStats()['total_users'];
    }

    public function type(): Contest\AbstractContest {
        return $this->info()['type'];
    }

    public function hasBonusPool(): bool {
        return !is_null($this->info()['bonus_pool']);
    }

    public function bonusPoolTotal(): float {
        return $this->info()['bonus_pool']?->total() ?? 0.0;
    }

    public function bonusStatus(): string {
        return $this->info()['bonus_status'];
    }

    public function bonusPerContest(): float {
        return $this->info()['bonus_contest'];
    }

    public function bonusPerContestRatio(): float {
        return $this->info()['bonus_contest_ratio'];
    }

    public function bonusPerContestValue(): int {
        $totalUsers = $this->totalUsers();
        return $totalUsers ? floor($this->bonusPoolTotal() *  $this->bonusPerContestRatio() / $totalUsers) : 0;
    }

    public function bonusPerEntry(): float {
        return $this->info()['bonus_per_entry'];
    }

    public function bonusPerEntryRatio(): float {
        return $this->info()['bonus_per_entry_ratio'];
    }

    public function bonusPerEntryValue(): int {
        $totalEntries = $this->totalEntries();
        return $totalEntries ? floor($this->bonusPoolTotal() * $this->bonusPerEntryRatio() / $totalEntries) : 0;
    }

    public function bonusPerUser(): float {
        return $this->info()['bonus_user'];
    }

    public function bonusPerUserRatio(): float {
        return $this->info()['bonus_user_ratio'];
    }

    public function bonusPerUserValue(): int {
        $totalEnabledUsers = (new Stats\Users())->enabledUserTotal();
        return $totalEnabledUsers ? floor($this->bonusPoolTotal() * $this->bonusPerUserRatio() / $totalEnabledUsers) : 0;
    }

    public function isOpen(): bool {
        return (bool)$this->info()['is_open'];
    }

    public function modify(): bool {
        $success = parent::modify();
        if ($success) {
            self::$db->prepared_query("
                UPDATE bonus_pool bp
                INNER JOIN contest_has_bonus_pool chbp USING (bonus_pool_id)
                INNER JOIN  contest c USING (contest_id)
                SET
                    bp.name       = c.name,
                    bp.since_date = c.date_begin,
                    bp.until_date = c.date_end
                WHERE c.contest_id = ?
                ", $this->id()
            );
            self::$cache->delete_value(Manager\Bonus::CACHE_OPEN_POOL);
        }
        return $success;
    }

    public function modifyBonusPool(int $contest, int $entry, int $user): int {
        self::$db->prepared_query("
            UPDATE contest_has_bonus_pool SET
                bonus_contest = ?,
                bonus_per_entry = ?,
                bonus_user = ?
            WHERE contest_id = ?
            ", $contest, $entry, $user, $this->id
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->flush();
        }
        return $affected;
    }

    public function calculateLeaderboard(): int {
        /* only called from scheduler, don't need to worry how long this takes */
        [$subquery, $args] = $this->type()->ranker();
        self::$db->begin_transaction();
        self::$db->prepared_query('DELETE FROM contest_leaderboard WHERE contest_id = ?', $this->id);
        self::$db->prepared_query("
            INSERT INTO contest_leaderboard
                (contest_id, user_id, entry_count, last_entry_id)
            SELECT ?, LADDER.userid, LADDER.nr, T.ID
            FROM torrents_group TG
            LEFT JOIN torrents_artists TA ON (TA.GroupID = TG.ID)
            LEFT JOIN artists_group AG ON (AG.ArtistID = TA.ArtistID)
            INNER JOIN torrents T ON (T.GroupID = TG.ID)
            INNER JOIN (
                $subquery
            ) LADDER on (LADDER.last_torrent = T.ID)
            GROUP BY
                LADDER.nr,
                T.ID,
                TG.Name,
                T.created
            ", $this->id, ...$args
        );
        $n = self::$db->affected_rows();
        self::$db->commit();
        /* recache the pages */
        $pages = range(0, (int)(ceil($n)/CONTEST_ENTRIES_PER_PAGE) - 1);
        foreach ($pages as $p) {
            self::$cache->delete_value(sprintf(self::CONTEST_LEADERBOARD_CACHE_KEY, $this->id, $p));
            $this->type()->leaderboard(CONTEST_ENTRIES_PER_PAGE, $p); /** @phpstan-ignore-line */
        }
        return $n;
    }

    public function paymentReady(): bool {
        return $this->info()['payout_ready'] === 1;
    }

    public function setPaymentReady(): int {
        self::$db->prepared_query('
            UPDATE contest_has_bonus_pool SET
                status = ?
            WHERE contest_id = ?
            ', 'ready', $this->id
        );
        self::$cache->delete_value(sprintf(self::CACHE_CONTEST, $this->id));
        return self::$db->affected_rows();
    }

    public function setPaymentClosed(): int {
        self::$db->prepared_query('
            UPDATE contest_has_bonus_pool SET
                status = ?
            WHERE contest_id = ?
            ', 'paid', $this->id
        );
        self::$cache->delete_value(sprintf(self::CACHE_CONTEST, $this->id));
        return self::$db->affected_rows();
    }

    public function doPayout(Manager\User $userMan): int {
        $enabledUserBonus = $this->bonusPerUserValue();
        $contestBonus     = $this->bonusPerContestValue();
        $perEntryBonus    = $this->bonusPerEntryValue();

        $report = fopen(TMPDIR . "/payout-contest-" . $this->id . ".txt", 'a');
        fprintf($report, "# user=%d contest=%d entry=%d\n", $enabledUserBonus, $contestBonus, $perEntryBonus);

        $participants = $this->type()->userPayout($enabledUserBonus, $contestBonus, $perEntryBonus);
        foreach ($participants as $p) {
            $user = new User($p['ID']);
            $totalGain = $enabledUserBonus;
            if ($p['total_entries']) {
                $totalGain += $contestBonus + ($perEntryBonus * $p['total_entries']);
            }
            $log = date('Y-m-d H:i:s') ." {$user->label()} n={$p['total_entries']} t={$totalGain}";
            if ($user->hasAttr('no-fl-gifts') || $user->hasAttr('disable-bonus-points')) {
                fwrite($report, "$log DECLINED\n");
                continue;
            }
            fwrite($report, "$log DISTRIBUTED\n");
            if (DEBUG_CONTEST_PAYOUT) {
                continue;
            }
            $userMan->sendPM($p['ID'], 0,
                "You have received " . number_format($totalGain, 2) . " bonus points!",
                self::$twig->render('contest/payout-uploader.twig', [
                    'contest'         => $this,
                    'contest_bonus'   => $contestBonus,
                    'enabled_bonus'   => $enabledUserBonus,
                    'per_entry_bonus' => $perEntryBonus,
                    'total_entries'   => $p['total_entries'],
                    'username'        => $user->username(),
                ])
            );
            (new User\Bonus($user))->addPoints($totalGain);
            $user->addStaffNote(number_format($totalGain) . " BP added for {$p['total_entries']} entries in {$this->name()}")
                ->modify();
        }
        fclose($report);
        return count($participants);
    }
}
