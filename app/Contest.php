<?php

namespace Gazelle;

use Gazelle\Enum\SourceDB;

class Contest extends BaseObject {
    final public const pkName        = 'contest_id';
    final public const tableName     = 'contest';
    final public const CACHE_CONTEST = 'contestv2_%d';
    final public const CACHE_STATS   = 'contest_stats_%d';
    final public const CONTEST_LEADERBOARD_CACHE_KEY = 'contest_leaderboard_%d_%d';

    protected array $stats; /* entries, users */

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::CACHE_CONTEST, $this->id),
            sprintf(self::CACHE_STATS, $this->id),
        ]);
        unset($this->info, $this->stats);
        return $this;
    }

    public function link(): string {
        return "<a href=\"{$this->url()}\">{$this->name()}</a>";
    }

    public function location(): string {
        return "contest.php?id={$this->id}";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_CONTEST, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT t.name AS contest_type,
                    c.name,
                    c.banner,
                    c.description,
                    c.display,
                    c.date_begin,
                    c.date_end,
                    cbp.bonus_pool_id AS bonus_pool_id,
                    coalesce(cbp.status, 'none') AS bonus_status,
                    cbp.bonus_user,
                    cbp.bonus_contest,
                    cbp.bonus_per_entry,
                    IF (now() BETWEEN c.date_begin AND c.date_end, 1, 0) AS is_open,
                    IF (
                        cbp.bonus_pool_id IS NOT NULL
                            AND cbp.status = ?
                            AND now() > c.date_end,
                        1,
                        0
                    ) AS payout_ready
                FROM contest c
                INNER JOIN contest_type t USING (contest_type_id)
                LEFT JOIN contest_has_bonus_pool cbp USING (contest_id)
                WHERE c.contest_id = ?
                ", 'open', $this->id
            );
            $info['is_open'] = (bool)$info['is_open'];
            self::$cache->cache_value($key, $info, 86400 * 3);
        }

        // upload-flac-no-single => UploadFlacNoSingle
        $className = '\\Gazelle\\Contest\\'
            . implode('', array_map('ucfirst', explode('-', $info['contest_type'])));
        $info['type'] = new $className($this->id, $info['date_begin'], $info['date_end']);

        if ($info['bonus_pool_id']) {
            $info['bonus_pool'] = new BonusPool($info['bonus_pool_id']);
            $sum = 0;
            foreach (['bonus_contest', 'bonus_per_entry', 'bonus_user'] as $field) {
                $info[$field] = (int)$info[$field];
                $sum += $info[$field];
            }
            // calculate the ratios of how the bonus pool is carved up
            // the sum of the ratios adds up to 1.0
            $info['bonus_user_ratio']      = $info['bonus_user'] / $sum;
            $info['bonus_contest_ratio']   = $info['bonus_contest'] / $sum;
            $info['bonus_per_entry_ratio'] = 1 - ($info['bonus_user_ratio'] + $info['bonus_contest_ratio']);
        } else {
            $info['bonus_pool']            = null;
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

    public function isOpen(): bool {
        return $this->info()['is_open'];
    }

    public function leaderboard(int $limit, int $offset): array {
        return $this->type()->leaderboard($limit, $offset); /** @phpstan-ignore-line */
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function paymentReady(): bool {
        return $this->info()['payout_ready'] === 1;
    }

    public function type(): Contest\AbstractContest {
        return $this->info()['type'];
    }

    public function bonusPool(): ?BonusPool {
        return $this->info()['bonus_pool'];
    }

    public function hasBonusPool(): bool {
        return $this->bonusPool() instanceof BonusPool;
    }

    public function bonusPoolTotal(): int {
        return (int)$this->bonusPool()?->total();
    }

    public function bonusStatus(): string {
        return $this->info()['bonus_status'];
    }

    public function bonusPerContest(): int {
        return $this->info()['bonus_contest'];
    }

    public function bonusPerContestRatio(): float {
        return $this->info()['bonus_contest_ratio'];
    }

    public function bonusPerContestValue(): int {
        $totalUsers = $this->totalUsers();
        return $totalUsers ?
            (int)floor(
                $this->bonusPoolTotal() *  $this->bonusPerContestRatio()
                / $totalUsers
            )
            : 0;
    }

    public function bonusPerEntry(): int {
        return $this->info()['bonus_per_entry'];
    }

    public function bonusPerEntryRatio(): float {
        return $this->info()['bonus_per_entry_ratio'];
    }

    public function bonusPerEntryValue(): int {
        $totalEntries = $this->totalEntries();
        return $totalEntries
            ? (int)floor(
                $this->bonusPoolTotal() * $this->bonusPerEntryRatio()
                / $totalEntries
            )
            : 0;
    }

    public function bonusPerUser(): int {
        return $this->info()['bonus_user'];
    }

    public function bonusPerUserRatio(): float {
        return $this->info()['bonus_user_ratio'];
    }

    public function bonusPerUserValue(): int {
        $totalEnabledUsers = new Stats\Users()->enabledUserTotal();
        return $totalEnabledUsers
            ? (int)floor(
                $this->bonusPoolTotal() * $this->bonusPerUserRatio()
                / $totalEnabledUsers
            )
            : 0;
    }

    public function rank(User $user): ?array {
        $page   = 0;
        while (true) {
            $leaderboard = $this->leaderboard(CONTEST_ENTRIES_PER_PAGE, $page * CONTEST_ENTRIES_PER_PAGE);
            if (!$leaderboard) {
                break;
            }
            for ($i = 0, $max = count($leaderboard); $i < $max; $i++) {
                if ($user->id == $leaderboard[$i]['user_id']) {
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

    public function calculateLeaderboard(): int {
        /* only called from scheduler, don't need to worry how long this takes */
        [$subquery, $args] = $this->type()->ranker();
        if ($this->type()->rankerSource() === SourceDB::mysql) {
            self::$db->begin_transaction();
            self::$db->prepared_query(
                'DELETE FROM contest_leaderboard WHERE contest_id = ?',
                $this->id,
            );
            self::$db->prepared_query("
                INSERT INTO contest_leaderboard
                    (contest_id, user_id, entry_count, last_entry_id)
                SELECT ?, LADDER.user_id, LADDER.nr, t.ID
                FROM torrents_group tg
                LEFT JOIN torrents_artists ta ON (ta.GroupID = tg.ID)
                INNER JOIN torrents t ON (t.GroupID = tg.ID)
                INNER JOIN (
                    $subquery
                ) LADDER on (LADDER.last_torrent = t.ID)
                GROUP BY
                    LADDER.user_id,
                    LADDER.nr,
                    t.ID,
                    tg.Name,
                    t.created
                ", $this->id, ...$args
            );
            $n = self::$db->affected_rows();
            self::$db->commit();
        } else {
            // a transaction is not possible across databases, just wing it
            self::$db->prepared_query(
                'DELETE FROM contest_leaderboard WHERE contest_id = ?',
                $this->id,
            );
            $n = $this->pg()->query(<<<END_SQL
                insert into relay.contest_leaderboard
                    (contest_id, user_id, entry_count, last_entry_id)
                select $1, LADDER.user_id, LADDER.nr, t."ID"
                from relay.torrents_group tg
                left join relay.torrents_artists ta ON (ta."GroupID" = tg."ID")
                inner join relay.torrents t ON (t."GroupID" = tg."ID")
                inner join (
                    $subquery
                ) LADDER on (LADDER.last_torrent = t."ID")
                group by
                    LADDER.user_id,
                    LADDER.nr,
                    t."ID",
                    tg."Name",
                    t.created
                END_SQL,
                    $this->id,
                    ...$args
            );
        }
        /* recache the pages */
        $pages = range(0, (int)(ceil($n) / CONTEST_ENTRIES_PER_PAGE) - 1);
        foreach ($pages as $p) {
            self::$cache->delete_value(sprintf(self::CONTEST_LEADERBOARD_CACHE_KEY, $this->id, $p));
            $this->type()->leaderboard(CONTEST_ENTRIES_PER_PAGE, $p); /** @phpstan-ignore-line */
        }
        return $n;
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

    public function doPayout(
        bool         $dryrun  = false,
        Manager\User $manager = new Manager\User(),
    ): int {
        $this->flush(); // force a fresh reload from the db
        $report = fopen(TMPDIR . "/payout-contest-" . $this->id . ".txt", 'a');
        if ($report === false) {
            return 0;
        }
        if (!$this->hasBonusPool()) {
            fprintf($report, "# no bonus pool to distribute\n");
            fclose($report);
            return 0;
        }
        $enabledUserBonus = $this->bonusPerUserValue();
        $contestBonus     = $this->bonusPerContestValue();
        $perEntryBonus    = $this->bonusPerEntryValue();

        fprintf($report, "# user=%d contest=%d entry=%d\n", $enabledUserBonus, $contestBonus, $perEntryBonus);

        $participants = $this->type()->userPayout();
        foreach ($participants as $p) {
            $user = $manager->findById($p['user_id']);
            if (is_null($user)) {
                fwrite($report, "user {$p['user_id']} NOT FOUND\n");
                continue;
            }
            $totalGain    = $enabledUserBonus;
            $totalEntries = $p['total_entries'];
            if ($totalEntries) {
                $totalGain += $contestBonus + ($perEntryBonus * $totalEntries);
            }
            $log = date('Y-m-d H:i:s') . " {$user->label()} n={$totalEntries} t={$totalGain}";
            if ($user->hasAttr('no-fl-gifts') || $user->hasAttr('disable-bonus-points')) {
                fwrite($report, "$log DECLINED\n");
                continue;
            }
            fwrite($report, "$log DISTRIBUTED\n");
            if ($dryrun) {
                continue;
            }
            $user->inbox()->createSystem(
                "You have received " . number_format($totalGain, 2) . " bonus points!",
                self::$twig->render('contest/payout-uploader.bbcode.twig', [
                    'contest'         => $this,
                    'contest_bonus'   => $contestBonus,
                    'enabled_bonus'   => $enabledUserBonus,
                    'per_entry_bonus' => $perEntryBonus,
                    'total_entries'   => $totalEntries,
                    'username'        => $user->username(),
                ])
            );
            new User\Bonus($user)->addPoints($totalGain);
            $user->addStaffNote(
                number_format($totalGain)
                    . " BP added for {$totalEntries} entries in {$this->name()}"
            )->modify();
        }
        fclose($report);
        return count($participants);
    }

    public function modify(): bool {
        $success = parent::modify();
        if ($success) {
            self::$db->prepared_query("
                UPDATE bonus_pool bp
                INNER JOIN contest_has_bonus_pool chbp USING (bonus_pool_id)
                INNER JOIN contest c USING (contest_id)
                SET
                    bp.name       = c.name,
                    bp.since_date = c.date_begin,
                    bp.until_date = c.date_end
                WHERE c.contest_id = ?
                ", $this->id
            );
            self::$cache->delete_value(Manager\Bonus::CACHE_OPEN_POOL);
        }
        return $success;
    }

    public function remove(): int {
        // mysql is really dumb when it comes to foreign keys
        /* This does not work:
        self::$db->prepared_query("
            DELETE c, cl, chbp, bp
            FROM contest c
            LEFT JOIN contest_leaderboard cl USING (contest_id)
            LEFT JOIN contest_has_bonus_pool chbp USING (contest_id)
            LEFT JOIN bonus_pool bp USING (bonus_pool_id)
            WHERE c.contest_id = ?
            ", $this->id
        );
        */
        self::$db->begin_transaction();
        $affected = 0;
        if ($this->hasBonusPool()) {
            $pool = $this->bonusPool();
            self::$db->prepared_query("
                DELETE FROM contest_has_bonus_pool WHERE contest_id = ?
                ", $this->id
            );
            $affected += self::$db->affected_rows();
            self::$db->prepared_query("
                DELETE FROM bonus_pool WHERE bonus_pool_id = ?
                ", $pool->id
            );
            $affected += self::$db->affected_rows();
        }
        self::$db->prepared_query("
            DELETE FROM contest_leaderboard WHERE contest_id = ?
            ", $this->id
        );
        $affected += self::$db->affected_rows();
        self::$db->prepared_query("
            DELETE FROM contest WHERE contest_id = ?
            ", $this->id
        );
        $affected += self::$db->affected_rows();
        self::$db->commit();
        $this->flush();
        return $affected;
    }
}
