<?php

namespace Gazelle\Top10;

class Donor extends \Gazelle\Base {

    public function getTopDonors($limit) {
        return $this->db->prepared_query('
            SELECT UserID, TotalRank, Rank, SpecialRank, DonationTime, Hidden
            FROM users_donor_ranks
            WHERE TotalRank > 0
            ORDER BY TotalRank DESC
            LIMIT ?',
            $limit);
    }
}
