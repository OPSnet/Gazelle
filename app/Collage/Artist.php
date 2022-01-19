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
        $this->entryTotal = count($artists);

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
        return $this->entryTotal;
    }
}
