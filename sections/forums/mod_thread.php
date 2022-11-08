<?php

/*********************************************************************\
//--------------Mod thread-------------------------------------------//

This page gets called if we're editing a thread.

Known issues:
If multiple threads are moved before forum activity occurs then
threads will linger with the 'Moved' flag until they're knocked off
the front page.

\*********************************************************************/

if (!$Viewer->permitted('site_moderate_forums') && empty($_POST['transition'])) {
    error(403);
}
authorize();

$forumMan = new Gazelle\Manager\Forum;

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
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
$locked    = (bool)isset($_POST['locked']);
$newPinned = isset($_POST['sticky']) ? 1 : 0;
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
    } else {
        // Permissions are handled inside forums.class.php
        $transitions = $forumMan->forumTransitionList($Viewer);
        if (!isset($transitions[$transId])) {
            error(0);
        } else {
            $transition = $transitions[$transId];
            if ($transition['source'] != $forum->id()) {
                error(403);
            }
            $newForum  = $forumMan->findById($transition['destination']);
            $locked    = $thread->isLocked();
            $newPinned = $thread->isPinned();
            $newRank   = $thread->pinnedRanking();
            $newTitle  = $thread->title();
            $action    = 'transitioning';
        }
    }
}

if ($locked && $Viewer->permitted('site_moderate_forums')) {
    $thread->clearUserLastRead();
}

$thread->editThread($newForum ? $newForum->id() : $forum->id(), $newPinned, $newRank, $locked, $newTitle);

// topic notes and notifications
$notes = [];
$oldUrl = "[url=" . $forum->url() . "]" . $forum->name() . "[/url]";
if ($newForum) {
    $newUrl = "[url=" . $newForum->url() . "]" . $newForum->name() . "[/url]";
}
switch ($action ?? null) {
    case 'transitioning':
        $notes[] = "Moved from $oldUrl to $newUrl (" . $transition['label'] . " transition)";
        break;
    default:
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
        if ($newForum && $newForum->id() != $forum->id()) {
            $notes[] = "Moved from $oldUrl to $newUrl";
        }
        break;
}
if ($notes) {
    $thread->addThreadNote($Viewer->id(), implode("\n", $notes));
}

header("Location: {$thread->location()}&page=$page");
