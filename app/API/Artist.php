<?php

namespace Gazelle\API;

class Artist extends AbstractAPI {
    public function run() {
        if (!isset($_GET['artist_id'])) {
            json_error('Missing artist id');
        }

        $this->db->prepared_query("
            SELECT
                ArtistID,
                Name
            FROM
                artists_group
            WHERE
                ArtistID = ?", $_GET['artist_id']);
        if (!$this->db->has_results()) {
            json_error('Artist not found');
        }
        $artist = $this->db->next_record(MYSQLI_ASSOC, false);
        return $artist;
    }
}
