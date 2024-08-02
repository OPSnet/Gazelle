<?php

$artist = (new Gazelle\Manager\Artist())->findById((int)($_GET['id'] ?? 0));
if (is_null($artist)) {
    print json_die('missing artist id');
}
$limit = max(50, (int)($_GET['limit'] ?? 10));

echo (new Gazelle\Json\ArtistSimilar($artist, $limit))->response();
