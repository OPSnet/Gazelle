<?php

namespace Gazelle\UserRank\Dimension;

class DataUpload extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_uploadd';
    }

    public function selector(): string {
        return "
            SELECT greatest(uls.Uploaded - " . STARTING_UPLOAD . ", 0)
            FROM users_main um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE um.Enabled = '1'
                AND uls.Uploaded > " . STARTING_UPLOAD . "
            ORDER BY 1
            ";
    }
}
