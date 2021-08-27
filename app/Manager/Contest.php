<?php

namespace Gazelle\Manager;

class Contest extends \Gazelle\Base {

    public function create (array $info) {
        $this->db->prepared_query("
            INSERT INTO contest
                   (name, display, date_begin, date_end, contest_type_id, banner, description)
            VALUES (?,    ?,       ?,          ?,        ?,               ?,      ?)
            ", $info['name'], $info['display'], $info['date_begin'], $info['date_end'],
               $info['type'], $info['banner'], $info['description']
        );
        $contestId = $this->db->inserted_id();
        if (isset($info['pool'])) {
            $this->db->prepared_query("INSERT INTO bonus_pool (name, since_date, until_date) VALUES (?, ?, ?)",
                $info['name'], $info['date_begin'], $info['date_end']
            );
            $poolId = $this->db->inserted_id();
            $this->db->prepared_query("INSERT INTO contest_has_bonus_pool (contest_id, bonus_pool_id) VALUES (?, ?)",
                $contestId, $poolId
            );
        }
        return new \Gazelle\Contest($contestId);
    }

    /**
     * Get the different available contest types
     *
     * @return array [id, name]
     */
    public function contestTypes(): ?array {
        $this->db->prepared_query("SELECT contest_type_id as id, name FROM contest_type ORDER BY name");
        return $this->db->to_array('id', MYSQLI_ASSOC);
    }

    /**
     * Get the current contest
     *
     * @return \Gazelle\Contest (or null if no contest is running)
     */
    public function currentContest() {
        $current = $this->db->scalar("
            SELECT contest_id
            FROM contest c
            WHERE c.date_end = (SELECT max(date_end) FROM contest)
                AND c.date_end > now() - INTERVAL 2 WEEK
        ");
        return $current ? new \Gazelle\Contest($current) : null;
    }

    /**
     * Get all the contests, prior and current
     *
     * @return array [contest_id]
     */
    public function contestList(): array {
        $this->db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            INNER JOIN contest_type t USING (contest_type_id)
            LEFT JOIN contest_has_bonus_pool cbp USING (contest_id)
            ORDER BY c.date_begin DESC
         ");
         return array_map(fn($id) => new \Gazelle\Contest($id), $this->db->collect(0));
    }

    /**
     * Get the list of all contests in the past (open or not)
     *
     * @return array of \Gazelle\Contest
     */
    public function priorContests() {
        $this->db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            WHERE c.date_begin < NOW()
            /* AND ... we may want to think about excluding certain past contests */
            ORDER BY c.date_begin ASC
        ");
        return array_map(fn($id) => new \Gazelle\Contest($id), $this->db->collect(0));
    }

    /* --- SCHEDULED TASKS --- */

    /**
     * Recalculate the leaderboards of all the current (and recently closed) contests
     */
    public function calculateAllLeaderboards(): int {
        $this->db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            INNER JOIN contest_type t USING (contest_type_id)
            WHERE c.date_end > now() - INTERVAL 2 YEAR
        ");
        $contestList = $this->db->collect(0);
        foreach ($contestList as $id) {
            (new \Gazelle\Contest($id))->calculateLeaderboard();
        }
        return count($contestList);
    }

    /**
     * Redistribute the bonus points for a contest to the participants.
     */
    public function schedulePayout(\Twig\Environment $twig): int {
        $this->db->prepared_query("
            SELECT c.contest_id
            FROM contest c
            INNER JOIN contest_has_bonus_pool cbp USING (contest_id)
            WHERE c.date_end < now()
                AND cbp.status = ?
            ", 'ready'
        );
        $contests = array_map(fn($id) => new \Gazelle\Contest($id), $this->db->collect(0));
        $totalParticipants = 0;
        foreach ($contests as $contest) {
            $totalParticipants += $contest->doPayout($twig);
            $contest->setPaymentClosed();
        }
        return $totalParticipants;
    }
}
