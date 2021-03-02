<?php
authorize();

if (!check_perms('forums_polls_moderate')) {
    error(403, true);
}

$threadId = (int)$_POST['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(0, true);
}
$forum->moderatePoll($threadId, isset($_POST['feature']), isset($_POST['close']));

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? "forums.php?action=viewthread&threadid={$threadId}"));
