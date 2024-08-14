<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('forums_polls_moderate')) {
    error(403, true);
}
authorize();

$poll = (new Gazelle\Manager\ForumPoll())->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    error(0, true);
}
if (isset($_POST['ck-feat']) && isset($_POST['feature'])) {
    $poll->setField('Featured', $poll->isFeatured() ? null : date('Y-m-d H:i:s'));
}
if (isset($_POST['ck-close']) && isset($_POST['close'])) {
    $poll->setField('Closed', $poll->isClosed() ? '0' : '1');
}
$poll->modify();

header("Location: {$poll->location()}");
