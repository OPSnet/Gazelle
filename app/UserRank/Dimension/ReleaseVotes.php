<?php

namespace Gazelle\UserRank\Dimension;

class ReleaseVotes extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_releasevites';
    }

    public function selector(): string {
        return "
            SELECT DISTINCT n FROM (
                SELECT count(*) AS n
                FROM users_votes uv
                INNER JOIN users_main um ON (um.id = uv.userid)
                WHERE um.enabled = '1'
                GROUP BY um.id
            ) C
            ORDER BY 1
            ";
    }
}
