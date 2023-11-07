<?php

$limit  = (int)($_GET['count'] ?? 0);
$offset = (int)($_GET['offset'] ?? 0);

if ($limit <= 0 || $offset < 0 || $limit > 10) {
    // Never allow more than 10 items
    json_die('failure');
}

echo (new Gazelle\Json\News($limit, $offset))->setVersion(2)->response();
