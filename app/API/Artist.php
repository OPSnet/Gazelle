<?php

namespace Gazelle\API;

class Artist extends AbstractAPI {
    public function run(): array {
        if (!isset($_GET['artist_id'])) {
            json_error('Missing artist id');
        }

        $artist = new \Gazelle\Manager\Artist()->findById((int)$_GET['artist_id']);
        if (is_null($artist)) {
            json_error('Artist not found');
        }
        return [
            'ArtistID' => $artist->id,
            'Name'     => $artist->name(),
        ];
    }
}
