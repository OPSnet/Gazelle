<?php
/** @phpstan-var \Gazelle\User $Viewer */

$postId = (int)($_GET['post'] ?? 0);
$pm = (new Gazelle\Manager\StaffPM())->findByPostId($postId);
if (is_null($pm)) {
    error(404);
}
if (!$pm->visible($Viewer)) {
    error(403);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'username' => (new Gazelle\Manager\User())->findById((int)$pm->postUserId($postId))?->username(),
    'body'     => $pm->postBody($postId),
]);
