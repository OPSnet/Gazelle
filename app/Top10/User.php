<?php

declare(strict_types=1);

namespace Gazelle\Top10;

use Gazelle\Enum\UserStatus;
use Gazelle\Enum\UserTop10Key;

class User extends \Gazelle\Base {
    final public const CACHE_KEY = 'topusers_%s_%d';

    public function fetch(UserTop10Key $type, int $limit): array {
        $key  = sprintf(self::CACHE_KEY, $type->value, $limit);
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $list = $this->pg()->allex($sql = <<<END_SQL
                select
                    um."ID"          as id,
                    um.created       as created,
                    uls."Uploaded"   as uploaded,
                    uls."Downloaded" as downloaded,
                    usr.vote_size    as request_votes,
                    usr.bounty_total as request_fills,
                    ust.upload       as num_uploads,
                    abs(uls."Uploaded" - $1) / (extract(epoch from now()) - extract(epoch from um.created)) as up_speed,
                    uls."Downloaded" / (extract(epoch from now()) - extract(epoch from um.created))         as down_speed
                from relay.users_main              um
                inner join relay.users_leech_stats uls on (uls."UserID" = um."ID")
                inner join user_summary_request    usr on (usr.id_user = um."ID")
                inner join user_summary_tgroup     ust on (ust.id_user = um."ID")
                where um."Enabled" = $2
                    and (um."Paranoia" is null or (um."Paranoia" !~~ '%\"uploaded\"%' and um."Paranoia" !~~ '%\"downloaded\"%'))
                order by {$type->value} desc
                limit $3
                END_SQL,
                    STARTING_UPLOAD, UserStatus::enabled->value, $limit
            );
            self::$cache->cache_value($key, $list, 3600 * 12);
        }
        return $list;
    }
}
