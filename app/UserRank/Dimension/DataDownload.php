<?php

namespace Gazelle\UserRank\Dimension;

class DataDownload extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_download';
    }

    public function selector(): string {
        return "
            SELECT uls.Downloaded
            FROM users_main um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE um.Enabled = '1'
                AND uls.Downloaded > 0
            ORDER BY 1
            ";
    }
}
