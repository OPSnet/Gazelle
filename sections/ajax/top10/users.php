<?php

declare(strict_types=1);

namespace Gazelle;

$details = $_GET['details'] ?? 'all';
if (!in_array($details, ['all', 'ul', 'dl', 'numul', 'uls', 'dls'])) {
    json_die('failure', 'bad details parameter');
}

$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 100, 250])) {
    json_die('failure', 'bad limit parameter');
}

echo new Json\Top10\User($details, $limit)
    ->setVersion(2)
    ->response();
