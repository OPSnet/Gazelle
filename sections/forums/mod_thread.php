<?php

/*********************************************************************\
//--------------Mod thread-------------------------------------------//

This page gets called if we're editing a thread.

Known issues:
If multiple threads are moved before forum activity occurs then
threads will linger with the 'Moved' flag until they're knocked off
the front page.

\*********************************************************************/

authorize();

if (!check_perms('site_moderate_forums') && empty($_POST['transition'])) {
    error(403);
}
$threadId = (int)$_POST['threadid'];
if ($threadId < 1) {
    error(404);
}
$newForumId = (int)$_POST['forumid'];
if ($newForumId < 1 && !isset($_POST['transition'])) {
    error(404);
}
$newTitle = trim($_POST['title']);
if ($newTitle == '') {
    error("Title cannot be empty");
}

// Variables for database input
$page      = (int)$_POST['page'];
$locked    = isset($_POST['locked']) ? 1 : 0;
$newSticky = isset($_POST['sticky']) ? 1 : 0;
$newRank   = (int)$_POST['ranking'] ?? 0;

if (!$newSticky && $newRank > 0) {
    $newRank = 0;
} elseif ($newRank < 0) {
    error('Ranking cannot be a negative value');
}

$forum = new \Gazelle\Forum();
list($oldForumId, $oldName, $minClassWrite, $posts, $threadAuthorId, $oldTitle, $oldLocked, $oldSticky, $oldRank)
    = $forum->threadInfoExtended($threadId);
$forum->setForum($oldForumId);

if ($minClassWrite > $LoggedUser['Class']) {
    error(403);
}

// If we're deleting a thread
if (isset($_POST['delete'])) {
    if (!check_perms('site_admin_forums')) {
        error(403);
    }
    $forum->removeThread($threadId);
    header("Location: forums.php?action=viewforum&forumid=$newForumId");

} else { // We are editing it

    if (isset($_POST['transition'])) {
        $transId = (int)$_POST['transition'];
        if ($transId < 1) {
            error(0);
        } else {
            // Permissions are handled inside forums.class.php
            $transitions = Forums::get_transitions();
            $Debug->log_var($transitions);
            if (!isset($transitions[$transId])) {
                error(0);
            } else {
                $transition = $transitions[$transId];
                if ($transition['source'] != $oldForumId) {
                    error(403);
                }
                $newForumId = $transition['destination'];
                $newSticky  = $oldSticky;
                $locked     = $oldLocked;
                $newRank    = $oldRank;
                $newTitle   = $oldTitle;
                $action     = 'transitioning';
            }
        }
    }

    if ($locked && check_perms('site_moderate_forums')) {
        $forum->clearUserLastRead($threadId);
    }

    $forum->editThread($threadId, $newForumId, $newSticky, $newRank, $locked, $newTitle);

    // topic notes and notifications
    $notes = [];
    $oldUrl = "[url=" . site_url() . "forums.php?action=viewforum&forumid=$oldForumId]{$oldName}[/url]";
    $newUrl = "[url=" . site_url() . "forums.php?action=viewforum&forumid=$newForumId]{$newName}[/url]";
    switch ($action ?? null) {
        case 'transitioning':
            $notes[] = "Moved from $oldUrl to $newUrl (" . $transition['label'] . " transition)";
            break;
        default:
            if ($oldTitle != $newTitle) {
                $notes[] = "Title edited from \"$oldTitle\" to \"$newTitle\"";
            }
            if ($oldLocked != $locked) {
                $notes[] = $oldLocked ? 'Unlocked' : 'Locked';
            }
            if ($oldSticky != $newSticky) {
                $notes[] = $oldSticky ? 'Unstickied' : 'Stickied';
            }
            if ($oldRank != $newRank) {
                $notes[] = "Ranking changed from $oldRank to $newRank";
            }
            if ($newForumId != $oldForumId) {
                $notes[] = "Moved from $oldUrl to $newUrl";
            }
            break;
    }
    if ($notes) {
        $forum->addThreadNote($threadId, $LoggedUser['ID'], implode("\n", $notes));
    }
    header("Location: forums.php?action=viewthread&threadid=$threadId&page=$page");
}
