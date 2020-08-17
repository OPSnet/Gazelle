<?php

namespace Gazelle\UserRank\Dimension;

class ForumPosts extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_forumposts';
    }

    public function selector(): string {
        return "
            SELECT count(*)
            FROM users_main AS um
            INNER JOIN forums_posts AS p ON (p.AuthorID = um.ID)
            WHERE um.Enabled = '1'
            GROUP BY um.ID
            ORDER BY 1
            ";
    }
}
