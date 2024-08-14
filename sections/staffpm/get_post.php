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

header('Content-type: text/plain');
echo $pm->postBody($postId);
