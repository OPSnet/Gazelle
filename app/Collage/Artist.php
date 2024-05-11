<?php

namespace Gazelle\Collage;

class Artist extends AbstractCollage {
    public function entryTable(): string { return 'collages_artists'; }
    public function entryColumn(): string { return 'ArtistID'; }

    public function load(): int {
        self::$db->prepared_query("
            SELECT ca.ArtistID,
                aa.Name,
                coalesce(wa.Image, '') AS Image,
                ca.UserID,
                ca.Sort,
                ca.AddedOn AS created
            FROM collages_artists    AS ca
            INNER JOIN artists_group AS ag USING (ArtistID)
            INNER JOIN artists_alias    aa ON (ag.PrimaryAlias = aa.AliasID)
            LEFT JOIN wiki_artists   AS wa USING (RevisionID)
            WHERE ca.CollageID = ?
            ORDER BY ca.Sort
            ", $this->holder->id()
        );
        $artists = self::$db->to_array('ArtistID', MYSQLI_ASSOC, false);
        $total = count($artists);

        $this->artists      = [];
        $this->contributors = [];
        $this->created      = [];
        foreach ($artists as $artist) {
            if (!isset($this->artists[$artist['ArtistID']])) {
                $this->created[$artist['ArtistID']] = $artist['created'];
                $this->artists[$artist['ArtistID']] = [
                    'count'    => 0,
                    'id'       => $artist['ArtistID'],
                    'image'    => $artist['Image'],
                    'name'     => $artist['Name'],
                    'sequence' => $artist['Sort'],
                    'user_id'  => $artist['UserID'],
                ];
            }
            $this->artists[$artist['ArtistID']]['count']++;

            if (!isset($this->contributors[$artist['UserID']])) {
                $this->contributors[$artist['UserID']] = 0;
            }
            $this->contributors[$artist['UserID']]++;
        }
        arsort($this->contributors);
        return $total;
    }

    public function entryList(): array {
        if (!isset($this->artists)) {
            $this->load();
        }
        return array_keys($this->artists);
    }

    public function nameList(): array {
        return array_map(
            fn($a) => [
                'id'    => $a['id'],
                'name'  => $a['name'],
                'image' => $a['image'],
            ], $this->artists
        );
    }

    protected function flushTarget(int $artistId): void {
        $this->flushAll([
            "artists_collages_$artistId",
            "artists_collages_personal_$artistId",
        ]);
    }

    public function rebuildTagList(): array {
        self::$db->prepared_query("
            SELECT tag.Name FROM (
                SELECT DISTINCT ta.GroupID
                FROM collages_artists ca
                INNER JOIN artists_alias aa USING (ArtistID)
                INNER JOIN torrents_artists ta ON (aa.AliasID = ta.AliasID)
                INNER JOIN artist_role ar USING (artist_role_id)
                WHERE ar.slug in ('main', 'remixer', 'composer', 'conductor', 'dj', 'producer', 'arranger')
                    AND ca.CollageID = ?
            ) G
            INNER JOIN torrents_tags tt USING (GroupID)
            INNER JOIN tags tag on (tag.ID = tt.TagID)
            GROUP BY tag.Name
            HAVING count(*) > 1
            ORDER BY count(*) desc, tag.Name
            LIMIT 8
            ", $this->id
        );
        return self::$db->collect(0, false);
    }

    public function remove(): int {
        self::$db->prepared_query("
            SELECT ArtistID FROM collages_artists WHERE CollageID = ?
            ", $this->id
        );
        $keys = array_merge(...array_map(
            fn ($id) => ["artists_collages_$id", "artists_collages_personal_$id"],
            self::$db->collect(0, false)
        ));
        $rows = parent::remove();
        $this->flushAll($keys);
        return $rows;
    }
}
