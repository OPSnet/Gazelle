<?php

if (!$Viewer->permitted('forums_polls_moderate')) {
    error(403, true);
}
authorize();

$poll = (new Gazelle\Manager\ForumPoll)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    error(0, true);
}
$poll->moderate(isset($_POST['feature']), isset($_POST['close']));

header('Location: ' . redirectUrl($poll->location()));
