<?php

namespace Gazelle;

class Contest extends Base {
    const CACHE_CONTEST = 'contest_%d';

    protected $id;
    protected $info;
    /** @var \Gazelle\Contest\AbstractContest */
    protected $type;
    protected $bonusPool;
    protected $totalEntries;
    protected $totalUsers;
    protected $bonusUser;
    protected $bonusContest;
    protected $bonusPerEntry;

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
        $key = sprintf(self::CACHE_CONTEST, $this->id);
        if (($this->info = $this->cache->get_value($key)) === false) {
            $this->db->prepared_query("
                SELECT t.name AS contest_type, c.name, c.banner, c.description, c.display, c.max_tracked, c.date_begin, c.date_end,
                    coalesce(cbp.bonus_pool_id, 0) AS bonus_pool,
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
            $this->info = $this->db->next_record(MYSQLI_ASSOC);
            $this->cache->cache_value($key, $this->info, 86400 * 3);
        }
        if (is_null($this->info)) {
            throw new Exception\ResourceNotFoundException($id);
        }
        try {
            // upload-flac-no-single => UploadFlacNoSingle
            $className = '\\Gazelle\\Contest\\' . implode('', array_map('ucfirst', explode('-', $this->info['contest_type'])));
            $this->type = new $className($this->id, $this->info['date_begin'], $this->info['date_end']);
        }
        catch (\Error $e) {
            throw new Exception\ResourceNotFoundException($e->getMessage() . " [id=$id]");
        }
        if ($this->info['bonus_pool']) {
            $this->bonusPool = $this->info['bonus_pool'] ? new \Gazelle\BonusPool($this->info['bonus_pool']) : null;
            // calculate the ratios of how the bonus pool is carved up
            // sum(bonUser + bonusContest + bonusPerEntry) == 1.0
            $bonusClaimSum       = $this->info['bonus_user'] + $this->info['bonus_contest'] + $this->info['bonus_per_entry'];
            $this->bonusUser     = $this->info['bonus_user'] / $bonusClaimSum;
            $this->bonusContest  = $this->info['bonus_contest'] / $bonusClaimSum;
            $this->bonusPerEntry = 1 - ($this->bonusUser + $this->bonusContest);
        }
    }

    public function save(array $params): int {
        $this->db->prepared_query("
            UPDATE contest SET
                name = ?, display = ?, max_tracked = ?, date_begin = ?, date_end = ?,
                contest_type_id = ?, banner = ?, description = ?
            WHERE ID = ?
            ", trim($params['name']), $params['display'], $params['maxtrack'], $params['date_begin'], $params['date_end'],
                $params['type'], trim($params['banner']), trim($params['description']),
                $this->id
        );
        $success = $this->db->affected_rows();
        if (isset($params['payment'])) {
            $this->setPaymentReady();
        }
        $this->cache->delete_value(sprintf(self::CACHE_CONTEST, $this->id));
        return $success;
    }

    public function id(): int {
        return $this->id;
    }

    public function contestType(): string {
        return $this->info['contest_type'];
    }

    public function banner(): string {
        return $this->info['banner'];
    }

    public function dateBegin(): string {
        return $this->info['date_begin'];
    }

    public function dateEnd(): string {
        return $this->info['date_end'];
    }

    public function display(): int {
        return $this->info['display'];
    }

    public function calculateLeaderboard(): int {
        return $this->type->calculateLeaderboard();
    }

    public function leaderboard(): array {
        // may be a trait, so no access to $this
        return $this->type->leaderboard($this->maxTracked());
    }

    public function maxTracked(): int {
        return $this->info['max_tracked'];
    }

    public function name(): string {
        return $this->info['name'];
    }

    public function description(): string {
        return $this->info['description'];
    }

    public function totalEntries(): int {
        return $this->type->totalEntries();
    }

    public function totalUsers(): int {
        return $this->type->totalUsers();
    }

    public function hasBonusPool(): bool {
        return !is_null($this->bonusPool);
    }

    public function bonusPoolTotal(): float {
        static $total;
        if (is_null($total)) {
            $total = $this->bonusPool ? $this->bonusPool->total() : 0.0;
        }
        return $total;
    }

    public function bonusStatus(): string {
        return $this->info['bonus_status'];
    }

    public function bonusPerUserValue(): int {
        return $this->info['bonus_user'];
    }

    public function bonusPerContestValue(): int {
        return $this->info['bonus_contest'];
    }

    public function bonusPerEntryValue(): int {
        return $this->info['bonus_per_entry'];
    }

    public function bonusPerUser(): float {
        return $this->bonusPoolTotal() * $this->bonusUser / (new Manager\User())->getEnabledUsersCount();
    }

    public function bonusPerContest(): float {
        return $this->bonusPoolTotal() *  $this->bonusContest / $this->totalUsers();
    }

    public function bonusPerEntry(): float {
        return $this->bonusPoolTotal() * $this->bonusPerEntry / $this->totalEntries();
    }

    public function isOpen(): bool {
        return $this->info['is_open'] === 1;
    }

    public function paymentReady(): bool {
        return $this->info['payout_ready'] === 1;
    }

    public function setPaymentReady() {
        $this->db->prepared_query('
            UPDATE contest_has_bonus_pool SET
                status = ?
            WHERE contest_id = ?
            ', 'ready', $this->id
        );
        $this->cache->delete_value(sprintf(self::CACHE_CONTEST, $this->id));
        return $this->db->affected_rows();
    }

    public function setPaymentClosed() {
        $this->db->prepared_query('
            UPDATE contest_has_bonus_pool SET
                status = ?
            WHERE contest_id = ?
            ', 'paid', $this->id
        );
        $this->cache->delete_value(sprintf(self::CACHE_CONTEST, $this->id));
        return $this->db->affected_rows();
    }

    public function doPayment(\Twig\Environment $twig) {
        $enabledUserBonus = $this->bonusPerUser();
        $contestBonus     = $this->bonusPerContest();
        $perEntryBonus    = $this->bonusPerEntry();

        $participants = $this->type->userPayout($enabledUserBonus, $contestBonus, $perEntryBonus);
        $bonus = new Bonus;
        $report = fopen(TMPDIR . "/payout-contest-" . $this->id . ".txt", 'a');
        foreach ($participants as $p) {
            $totalGain = $enabledUserBonus;
            if ($p['nr_entries']) {
                $totalGain += $contestBonus + ($perEntryBonus * $p['nr_entries']);
            }
            \Misc::send_pm(
                $p['ID'],
                0,
                "You have received " . number_format($totalGain, 2) . " bonus points!",
                \Text::full_format($twig->render('contest/payout-uploader.twig', [
                    'username'        => $p['Username'],
                    'date'            => ['begin' => $this->info['date_begin'], 'end' => $this->info['date_end']],
                    'enabled_bonus'   => $enabledUserBonus,
                    'contest_bonus'   => $contestBonus,
                    'per_entry_bonus' => $perEntryBonus,
                    'name'            => $this->info['name'],
                    'nr_entries'      => $p['nr_entries'],
                    'entries'         => $p['nr_entries'] == 1 ? 'entry' : 'entries',
                ]))
            );
            $bonus->addPoints($p['ID'], $totalGain);
            $this->db->prepared_query("
                UPDATE users_info SET
                    AdminComment = CONCAT(now(), ' - ', ?, AdminComment)
                WHERE UserID = ?
                ", number_format($totalGain, 2) . " BP added for {$p['nr_entries']} entries in {$this->info['name']}\n\n",
                    $p['ID']
            );
            fwrite($report, sqltime() . " {$p['Username']} ({$p['ID']}) n={$p['nr_entries']} t={$totalGain}\n");
            fflush($report);
        }
        fclose($report);
        return count($participants);
    }
}
