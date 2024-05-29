<?php

header('Content-Type: application/json; charset=utf-8');

$prefix = trim(urldecode($_GET['query'] ?? ''));
echo json_encode([
    'query'       => $prefix,
    'suggestions' => (new Gazelle\Manager\Artist())->autocompleteList($prefix),
]);
