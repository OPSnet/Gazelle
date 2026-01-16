<?php

namespace Gazelle\Top10;

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
            self::$db->prepared_query("
                SELECT
                    um.ID          AS id,
                    um.created     AS created,
                    uls.Uploaded   AS uploaded,
                    uls.Downloaded AS downloaded,
                    coalesce(us.request_vote_size, 0)    AS request_votes,
                    coalesce(us.request_bounty_total, 0) AS request_fills,
                    coalesce(us.upload_total, 0)         AS num_uploads,
                    abs(uls.Uploaded - ?) / (unix_timestamp() - unix_timestamp(um.created)) AS up_speed,
                    uls.Downloaded / (unix_timestamp() - unix_timestamp(um.created))        AS down_speed
                FROM users_main              um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                LEFT JOIN user_summary       us  ON (us.user_id = um.ID)
                WHERE um.Enabled = '1'
                    AND (um.Paranoia IS NULL OR (um.Paranoia NOT LIKE '%\"uploaded\"%' AND um.Paranoia NOT LIKE '%\"downloaded\"%'))
                GROUP BY um.ID
                ORDER BY {$orderBy} DESC
                LIMIT ?
                ", STARTING_UPLOAD, $limit
            );

            $list = self::$db->to_array(false, MYSQLI_ASSOC);
            self::$cache->cache_value(sprintf(self::CACHE_KEY, $type, $limit), $list, 3600 * 12);
        }
        return $list;
    }
}
