<?php
header('Content-Type: application/json; charset=utf-8');

$tagMan = new \Gazelle\Manager\Tag($DB, $Cache);
$word = $tagMan->sanitize($_GET['query']);

echo json_encode([
    'query' => $word,
    'suggestions' => $tagMan->autocompleteAsJson($word),
]);
