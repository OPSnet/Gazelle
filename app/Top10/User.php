<?php

namespace Gazelle\Top10;

use Gazelle\Enum\UserStatus;

class User extends \Gazelle\Base {
    final public const UPLOADERS      = 'uploaders';
    final public const DOWNLOADERS    = 'downloaders';
    final public const UPLOADS        = 'uploads';
    final public const REQUEST_VOTES  = 'request_votes';
    final public const REQUEST_FILLS  = 'request_fills';
    final public const UPLOAD_SPEED   = 'upload_speed';
    final public const DOWNLOAD_SPEED = 'download_speed';

    private const CACHE_KEY = 'topusers_%s_%d';

    private array $sortMap = [
        self::UPLOADERS      => 'uploaded',
        self::DOWNLOADERS    => 'downloaded',
        self::UPLOADS        => 'num_uploads',
        self::REQUEST_VOTES  => 'request_votes',
        self::REQUEST_FILLS  => 'request_fills',
        self::UPLOAD_SPEED   => 'up_speed',
        self::DOWNLOAD_SPEED => 'down_speed',
    ];

    public function fetch(string $type, int $limit): array {
        if (!array_key_exists($type, $this->sortMap)) {
            return [];
        }
        $list = self::$cache->get_value(sprintf(self::CACHE_KEY, $type, $limit));
        if ($list === false) {
            $orderBy = $this->sortMap[$type];
            $list = $this->pg()->allex(<<<END_SQL
                select
                    um."ID"          as id,
                    um.created       as created,
                    uls."Uploaded"   as uploaded,
                    uls."Downloaded" as downloaded,
                    usr.vote_size    as request_votes,
                    usr.bounty_total as request_fills,
                    ust.upload       as num_uploads,
                    abs(uls."Uploaded" - $1) / (unix_timestamp() - unix_timestamp(um.created)) as up_speed,
                    uls."Downloaded" / (unix_timestamp() - unix_timestamp(um.created))         as down_speed
                from relay.users_main              um
                inner join relay.users_leech_stats uls on (uls."UserID" = um."ID")
                inner join user_summary_request    usr on (usr.id_user = um."ID")
                inner join user_summary_tgroup     ust on (ust.id_user = um."ID")
                where um."Enabled" = $2
                    and (um."Paranoia" is null or (um."Paranoia" !~~ '%\"uploaded\"%' and um."Paranoia" !~~ '%\"downloaded\"%'))
                group by um.ID
                order by {$orderBy} desc
                limit $3
                END_SQL,
                    STARTING_UPLOAD, UserStatus::enabled->value, $limit
            );
            self::$cache->cache_value(sprintf(self::CACHE_KEY, $type, $limit), $list, 3600 * 12);
        }
        return $list;
    }
}
