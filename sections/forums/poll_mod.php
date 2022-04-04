<?php

if (!$Viewer->permitted('forums_polls_moderate')) {
    error(403, true);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(0, true);
}
$thread->moderatePoll(isset($_POST['feature']), isset($_POST['close']));

header('Location: ' . redirectUrl($thread->location()));
