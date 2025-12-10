<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (empty($_GET['query'])) {
    json_die("failure", "no query");
}
if (!$Viewer->permitted('site_collages_create')) {
    json_die("failure", "forbidden");
}

$fullName = rawurldecode($_GET['query']);

header('Content-Type: application/json');
echo json_encode([
    'query'       => $fullName,
    'suggestions' => new Manager\Collage()->autocomplete($fullName, isset($_GET['artist'])),
]);
