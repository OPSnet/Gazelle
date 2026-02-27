<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disablePosting()) {
    Error403::error('Your posting privileges have been removed.');
}
authorize();

$page = $_REQUEST['page'] ?? null;
if (!in_array($page, ['artist', 'collages', 'requests', 'torrents'])) {
    Error403::error();
}

$pageId = (int)($_REQUEST['pageid'] ?? 0);
if (!$pageId) {
    Error404::error();
}

$comment = new Manager\Comment()->create($Viewer, $page, $pageId, $_POST['quickpost']);
$subscription = new User\Subscription($Viewer);
if (isset($_POST['subscribe']) && !$subscription->isSubscribedComments($page, $pageId)) {
    $subscription->subscribeComments($page, $pageId);
}

header('Location: ' . $comment->location());
