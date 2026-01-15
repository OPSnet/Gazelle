<?php

namespace Gazelle\UserRank\Dimension;

class CommentTorrent extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_commenttorrent';
    }

    public function selector(): string {
        return <<<END_SQL
            select count(*)
            from relay.users_main     um
            INNER join relay.comments c on (c."AuthorID" = um."ID" and c."Page" = 'torrents')
            where um."Enabled" = '1'
            group by um."ID"
        END_SQL;
    }
}
