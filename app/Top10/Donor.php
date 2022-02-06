<?php

namespace Gazelle\Top10;

class Donor extends \Gazelle\Base {
    // TODO: move this method to Manager\Donation and kill this class
    public function getTopDonors($limit) {
        return self::$db->prepared_query("
            SELECT UserID, TotalRank, donor_rank, SpecialRank, DonationTime, Hidden
            FROM users_donor_ranks
            WHERE TotalRank > 0
            ORDER BY TotalRank DESC
            LIMIT ?
            ", $limit
        );
    }
}
