<?php

$details = $_GET['details'] ?? 'all';
if (!in_array($details, ['all', 'ut', 'ur', 'v'])) {
    json_die(['status' => 'bad details parameter']);
}

$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 100, 250])) {
    json_die(['status' => 'bad limit parameter']);
}

(new Gazelle\Json\Top10\Tag(
    details: $details,
    limit: $limit,
    manager: new Gazelle\Manager\Tag,
))
    ->setVersion(2)
    ->emit();
