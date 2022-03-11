<?php

namespace Gazelle\Collage;

class Artist extends AbstractCollage {

    public function entryTable(): string { return 'collages_artists'; }
    public function entryColumn(): string { return 'ArtistID'; }

    public function load(): int {
        self::$db->prepared_query("
            SELECT ca.ArtistID,
                ag.Name,
                IF(wa.Image is NULL, '', wa.Image) as Image,
                ca.UserID,
                ca.Sort
            FROM collages_artists    AS ca
            INNER JOIN artists_group AS ag USING (ArtistID)
            LEFT JOIN wiki_artists   AS wa USING (RevisionID)
            WHERE ca.CollageID = ?
            ORDER BY ca.Sort
            ", $this->holder->id()
        );
        $artists = self::$db->to_array('ArtistID', MYSQLI_ASSOC, false);
        $total = count($artists);

        foreach ($artists as $artist) {
            if (!isset($this->artists[$artist['ArtistID']])) {
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
        return array_keys($this->artists);
    }

    protected function flushTarget(int $artistId): void {
        $this->flushAll([
            "artists_collages_$artistId",
            "artists_collages_personal_$artistId",
        ]);
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
