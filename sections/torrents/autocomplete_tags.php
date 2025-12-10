<?php

declare(strict_types=1);

namespace Gazelle;

header('Content-Type: application/json');

$tagMan = new \Gazelle\Manager\Tag();
$word = $tagMan->sanitize($_GET['query']);

echo json_encode([
    'query' => $word,
    'suggestions' => $tagMan->autocompleteAsJson($word),
]);
