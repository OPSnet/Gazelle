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
$threadId = (int)$_POST['threadid'];
$forum = $forumMan->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!$Viewer->writeAccess($forum)) {
    error(403);
}

if (isset($_POST['delete'])) {
    if (!$Viewer->permitted('site_admin_forums')) {
        error(403);
    }
    $forum->removeThread($threadId);
    header('Location: ' . $forum->url());
    exit;
}

if (isset($_POST['forumid'])) {
    $newForum = $forumMan->findById((int)$_POST['forumid']);
    if (is_null($newForum) && !isset($_POST['transition'])) {
        error(404);
    }
}

$newTitle = trim($_POST['title']);
if (!isset($_POST['transition']) && $newTitle === '') {
    error("Title cannot be empty");
}

// Variables for database input
$page      = (int)$_POST['page'];
$locked    = isset($_POST['locked']) ? 1 : 0;
$newSticky = isset($_POST['sticky']) ? 1 : 0;
$newRank   = (int)($_POST['ranking'] ?? 0);

if (!$newSticky && $newRank > 0) {
    $newRank = 0;
} elseif ($newRank < 0) {
    error('Ranking cannot be a negative value');
}

$threadInfo = $forum->threadInfo($threadId);
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
            $newSticky = $threadInfo['isSticky'];
            $locked    = $threadInfo['isLocked'];
            $newRank   = $threadInfo['Ranking'];
            $newTitle  = $threadInfo['Title'];
            $action    = 'transitioning';
        }
    }
}

if ($locked && $Viewer->permitted('site_moderate_forums')) {
    $forum->clearUserLastRead($threadId);
}

$forumId = $newForum ? $newForum->id() : $forum->id();
$forum->editThread($threadId, $forumId, $newSticky, $newRank, $locked, $newTitle);

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
        if ($threadInfo['Title'] != $newTitle) {
            $notes[] = "Title edited from \"{$threadInfo['Title']}\" to \"$newTitle\"";
        }
        if ($threadInfo['isLocked'] != $locked) {
            $notes[] = $threadInfo['isLocked'] ? 'Unlocked' : 'Locked';
        }
        if ($threadInfo['isSticky'] != $newSticky) {
            $notes[] = $threadInfo['isSticky'] ? 'Unpinned' : 'Pinned';
        }
        if ($threadInfo['Ranking'] != $newRank) {
            $notes[] = "Ranking changed from {$threadInfo['Ranking']} to $newRank";
        }
        if ($newForum && $newForum->id() != $forum->id()) {
            $notes[] = "Moved from $oldUrl to $newUrl";
        }
        break;
}
if ($notes) {
    $forum->addThreadNote($threadId, $Viewer->id(), implode("\n", $notes));
}

header("Location: forums.php?action=viewthread&threadid=$threadId&page=$page");
