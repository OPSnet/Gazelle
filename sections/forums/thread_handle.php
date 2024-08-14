<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_moderate_forums') && empty($_POST['transition'])) {
    error(403);
}
authorize();

$forumMan = new Gazelle\Manager\Forum();

$thread = (new Gazelle\Manager\ForumThread())->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
$forum = $thread->forum();

if (!$Viewer->writeAccess($forum)) {
    error(403);
}

if (isset($_POST['delete'])) {
    if (!$Viewer->permitted('site_admin_forums')) {
        error(403);
    }
    $thread->remove();
    header('Location: ' . $forum->location());
    exit;
}

$newForum = null;
if (isset($_POST['forumid'])) {
    $newForum = $forumMan->findById((int)$_POST['forumid']);
    if (is_null($newForum) && !isset($_POST['transition'])) {
        error(404);
    }
}

$newTitle = trim($_POST['title'] ?? '');
if (!isset($_POST['transition']) && $newTitle === '') {
    error("Title cannot be empty");
}

// Variables for database input
$page      = (int)$_POST['page'];
$locked    = isset($_POST['locked']);
$newPinned = isset($_POST['sticky']);
$newRank   = (int)($_POST['ranking'] ?? 0);

if (!$newPinned && $newRank > 0) {
    $newRank = 0;
} elseif ($newRank < 0) {
    error('Ranking cannot be a negative value');
}

if (isset($_POST['transition'])) {
    $transId = (int)$_POST['transition'];
    if ($transId < 1) {
        error(0);
    }
    $transitions = (new Gazelle\Manager\ForumTransition())->threadTransitionList($Viewer, $thread);
    if (!isset($transitions[$transId])) {
        error(0);
    }
    $transition = $transitions[$transId];
    $newForum  = $forumMan->findById($transition->destinationId());
    $locked    = $thread->isLocked();
    $newPinned = $thread->isPinned();
    $newRank   = $thread->pinnedRanking();
    $newTitle  = $thread->title();
}

if ($locked && $Viewer->permitted('site_moderate_forums')) {
    $thread->clearUserLastRead();
}

$thread->editThread($newForum ?? $forum, $newPinned, $newRank, $locked, $newTitle);

// topic notes and notifications
$notes = [];
if ($thread->title() != $newTitle) {
    $notes[] = "Title edited from \"{$thread->title()}\" to \"$newTitle\"";
}
if ($thread->isLocked() != $locked) {
    $notes[] = $thread->isLocked() ? 'Unlocked' : 'Locked';
}
if ($thread->isPinned() != $newPinned) {
    $notes[] = $thread->isPinned() ? 'Unpinned' : 'Pinned';
}
if ($thread->pinnedRanking() != $newRank) {
    $notes[] = "Ranking changed from {$thread->pinnedRanking()} to $newRank";
}
if ($newForum?->id() != $forum->id()) {
    $note = "Moved from [url={$forum->url()}]{$forum->name()}[/url] to [url={$newForum->url()}]{$newForum->name()}[/url]";
    if (isset($transition)) {
        $note .= " ({$transition->label()}) transition)";
    }
    $notes[] = $note;
}

if ($notes) {
    $thread->addThreadNote($Viewer, implode("\n", $notes));
}

header("Location: {$thread->location()}&page=$page");
