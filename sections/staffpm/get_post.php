<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$postId = (int)($_GET['post'] ?? 0);
$pm = new Manager\StaffPM()->findByPostId($postId);
if (is_null($pm)) {
    Error404::error();
}
if (!$pm->visible($Viewer)) {
    Error403::error();
}

header('Content-Type: application/json');
echo json_encode([
    'username' => new Manager\User()->findById((int)$pm->postUserId($postId))?->username(),
    'body'     => $pm->postBody($postId),
]);
