<?php

namespace Gazelle;

class Donor {
    /** @var \DB_MYSQL */
    private $db;

    public function __construct (\DB_MYSQL $db) {
        $this->db = $db;
    }

    public function get_top_donors($limit) {
        return $this->db->prepared_query('
            SELECT UserID, TotalRank, Rank, SpecialRank, DonationTime, Hidden
            FROM users_donor_ranks
            WHERE TotalRank > 0
            ORDER BY TotalRank DESC
            LIMIT ?',
            $limit);
    }
}
