<?php

if (!$Viewer->permitted('forums_polls_moderate')) {
    error(403, true);
}
authorize();

$threadId = (int)$_POST['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(0, true);
}
$forum->moderatePoll($threadId, isset($_POST['feature']), isset($_POST['close']));

header('Location: ' . redirectUrl("forums.php?action=viewthread&threadid={$threadId}"));
