<?php

namespace Gazelle\Json\Bookmark;

class Artist extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\User\Bookmark $bookmark,
    ) {}

    public function payload(): array {
        self::$db->prepared_query("
            SELECT ag.ArtistID AS artistId,
                aa.Name        AS artistName
            FROM bookmarks_artists AS ba
            INNER JOIN artists_group AS ag USING (ArtistID)
            INNER JOIN artists_alias aa ON (ag.PrimaryAlias = aa.AliasID)
            WHERE ba.UserID = ?
            ", $this->bookmark->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
