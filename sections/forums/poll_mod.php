<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('forums_polls_moderate')) {
    Error403::error("You are not allowed to modify polls");
}
authorize();

$poll = new Manager\ForumPoll()->findById((int)($_POST['threadid'] ?? 0));
if ($poll === null) {
    Error404::error('Cannot find a poll for that thread');
}
if (isset($_POST['ck-feat'])) {
    $poll->setField('Featured', $poll->isFeatured() ? null : date('Y-m-d H:i:s'));
}
if (isset($_POST['ck-close']) && isset($_POST['close'])) {
    $poll->setField('Closed', $poll->isClosed() ? '0' : '1');
}
$poll->modify();

header("Location: {$poll->location()}");
