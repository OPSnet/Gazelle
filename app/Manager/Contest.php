<?php

namespace Gazelle\Manager;

class Contest extends \Gazelle\Base {
    public function create (
        string $banner,
        string $dateBegin,
        string $dateEnd,
        string $description,
        int $display,
        bool $hasPool,
        string $name,
        int $type,
    ): \Gazelle\Contest {
        self::$db->prepared_query("
            INSERT INTO contest
                   (name, display, date_begin, date_end, contest_type_id, banner, description)
            VALUES (?,    ?,       ?,          ?,        ?,               ?,      ?)
            ", $name, $display, $dateBegin, $dateEnd, $type, $banner, $description
        );
        $contestId = self::$db->inserted_id();
        if ($hasPool) {
            self::$db->prepared_query("
                INSERT INTO bonus_pool
                       (name, since_date, until_date)
                VALUES (?,    ?,          ?)
                ", $name, $dateBegin, $dateEnd
            );
            $poolId = self::$db->inserted_id();
            self::$db->prepared_query("INSERT INTO contest_has_bonus_pool (contest_id, bonus_pool_id) VALUES (?, ?)",
                $contestId, $poolId
            );
        }
        return $this->findById($contestId);
    }

    public function findById(int $contestId): ?\Gazelle\Contest {
        $id = self::$db->scalar("
            SELECT contest_id FROM contest WHERE contest_id = ?
            ", $contestId
        );
        return $id ? new \Gazelle\Contest($id) : null;
    }

    /**
     * Get the different available contest types
     *
     * @return array [id, name]
     */
    public function contestTypes(): ?array {
        self::$db->prepared_query("SELECT contest_type_id as id, name FROM contest_type ORDER BY name");
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Get the current contest
     */
    public function currentContest(): ?\Gazelle\Contest {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT contest_id
                FROM contest c
                WHERE c.date_end = (SELECT max(date_end) FROM contest)
                    AND c.date_end > now() - INTERVAL 2 WEEK
            ")
        );
    }

    /**
     * Get all the contests, prior and current
     *
     * @return array [contest_id]
     */
    public function contestList(): array {
        self::$db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            INNER JOIN contest_type t USING (contest_type_id)
            LEFT JOIN contest_has_bonus_pool cbp USING (contest_id)
            ORDER BY c.date_begin DESC
         ");
         return array_map(fn($id) => $this->findById($id), self::$db->collect(0));
    }

    /**
     * Get the list of all contests in the past (open or not)
     *
     * @return array of \Gazelle\Contest
     */
    public function priorContests() {
        self::$db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            WHERE c.date_begin < NOW()
            /* AND ... we may want to think about excluding certain past contests */
            ORDER BY c.date_begin ASC
        ");
        return array_map(fn($id) => $this->findById($id), self::$db->collect(0));
    }

    /* --- SCHEDULED TASKS --- */

    /**
     * Recalculate the leaderboards of all the current (and recently closed) contests
     */
    public function calculateAllLeaderboards(): int {
        self::$db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            INNER JOIN contest_type t USING (contest_type_id)
            WHERE c.date_end > now() - INTERVAL 2 YEAR
        ");
        $contestList = self::$db->collect(0, false);
        foreach ($contestList as $id) {
            $this->findById($id)->calculateLeaderboard();
        }
        return count($contestList);
    }

    /**
     * Redistribute the bonus points for a contest to the participants.
     */
    public function schedulePayout(User $userMan): int {
        self::$db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            INNER JOIN contest_has_bonus_pool cbp USING (contest_id)
            WHERE c.date_end < now()
                AND cbp.status = ?
            ", 'ready'
        );
        $contests = array_map(fn($id) => $this->findById($id), self::$db->collect(0));
        $totalParticipants = 0;
        foreach ($contests as $contest) {
            $totalParticipants += $contest->doPayout($userMan);
            $contest->setPaymentClosed();
        }
        return $totalParticipants;
    }
}
