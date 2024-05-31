<?php

namespace Gazelle\Search;

use Gazelle\Enum\UserTorrentSearch;

/**
 * Collect the IDs of torrents that a user has uploaded, snatched
 * or is seeding. This is used for creating ZIP downloads of the
 * corresponding .torrent files.
 */

class UserTorrent extends \Gazelle\Base {
    public function __construct(
        protected \Gazelle\User     $user,
        protected UserTorrentSearch $type,
    ) {}

    public function label(): string {
        return $this->type->value;
    }

    public function idList(): array {
        self::$db->prepared_query("
            SELECT DISTINCT t.ID
            FROM torrents AS t "
            . match ($this->type) {
                UserTorrentSearch::seeding => "
                    INNER JOIN xbt_files_users AS xfu ON (t.ID = xfu.fid)
                    WHERE xfu.remaining = 0
                        AND xfu.uid = ?",
                UserTorrentSearch::snatched => "
                    INNER JOIN xbt_snatched AS x ON (t.ID = x.fid)
                    WHERE x.uid = ?",
                default =>
                    "WHERE t.UserID = ?",
            }, $this->user->id()
        );
        return self::$db->collect(0, false);
    }
}
