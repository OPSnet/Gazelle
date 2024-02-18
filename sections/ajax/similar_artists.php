<?php

$limit  = (int)($_GET['limit'] ?? 10);
$artist = (new Gazelle\Manager\Artist())->findById((int)($_GET['id'] ?? 0));
if (is_null($artist) || !$limit) {
    print json_die('failure');
}

echo (new Gazelle\Json\ArtistSimilar($artist, $limit))->response();
