<?php

namespace Gazelle\API;

class Artist extends AbstractAPI {
    public function run() {
        if (empty($_GET['artist_id'])) {
            json_error('Missing artist id');
        }

        $artistMan = new \Gazelle\Manager\Artist();
        $artist = $artistMan->findById((int)$_GET['artist_id']);
        if (is_null($artist)) {
            json_error('Artist not found');
        }
        return [
            'ArtistID' => $artist->id(),
            'Name' => $artist->name(),
        ];
    }
}
