<?php

$details = $_GET['details'] ?? 'all';
if (!in_array($details, ['all', 'ul', 'dl', 'numul', 'uls', 'dls'])) {
    json_die('failure', 'bad details parameter');
}

$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 100, 250])) {
    json_die('failure', 'bad limit parameter');
}

echo (new Gazelle\Json\Top10\User(
    details: $details,
    limit:   $limit,
    stats:   new Gazelle\Stats\Users(),
    userMan: new Gazelle\Manager\User(),
))
    ->setVersion(2)
    ->response();
