<?php

namespace Gazelle\UserRank\Dimension;

class ForumPosts extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_forumposts';
    }

    public function selector(): string {
        return <<<END_SQL
            select count(*)
            from relay.users_main         um
            inner join relay.forums_posts p on (p."AuthorID" = um."ID")
            where um."Enabled" = '1'
            group by um."ID"
        END_SQL;
    }
}
