<?php

if (empty($_GET['query'])) {
    json_die("failure", "no query");
}
if (!$Viewer->permitted('site_collages_create')) {
    json_die("failure", "forbidden");
}

$fullName = rawurldecode($_GET['query']);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'query'       => $fullName,
    'suggestions' => (new Gazelle\Manager\Collage())->autocomplete($fullName, isset($_GET['artist'])),
]);
