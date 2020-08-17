<?php

namespace Gazelle\UserRank\Dimension;

class CommentTorrent extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_commenttorrent';
    }

    public function selector(): string {
        return "
            SELECT count(*)
            FROM users_main AS um
            INNER JOIN comments AS c ON (c.AuthorID = um.ID AND c.Page = 'torrents')
            WHERE um.Enabled = '1'
            GROUP BY um.ID
            ORDER BY 1
            ";
    }
}
