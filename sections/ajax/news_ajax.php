<?php

declare(strict_types=1);

namespace Gazelle;

$limit  = (int)($_GET['count'] ?? 0);
$offset = (int)($_GET['offset'] ?? 0);

if ($limit <= 0 || $offset < 0 || $limit > 10) {
    // Never allow more than 10 items
    json_die('failure');
}

echo new Json\News($limit, $offset)
    ->setVersion(2)
    ->response();
