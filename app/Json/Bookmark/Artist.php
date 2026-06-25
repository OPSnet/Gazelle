<?php

namespace Gazelle\Json\Bookmark;

class Artist extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\User\Bookmark $bookmark,
    ) {}

    public function payload(): array {
        $idList = $this->pg()->columnex("
            select id_artist from bookmark_artist where id_user = $1
            ", $this->bookmark->id
        );
        if ($idList === []) {
            return [];
        }
        $placeholder = placeholders($idList);
        self::$db->prepared_query("
            SELECT ag.ArtistID AS artistId,
                aa.Name        AS artistName
            FROM artists_group       ag
            INNER JOIN artists_alias aa ON (aa.AliasID = ag.PrimaryAlias)
            WHERE ag.ArtistID IN ($placeholder)
            ", ...$idList
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }
}
