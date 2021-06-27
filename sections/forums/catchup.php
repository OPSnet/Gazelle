<?php
authorize();
if (!isset($_GET['forumid']) || ($_GET['forumid'] != 'all' && !is_number($_GET['forumid']))) {
    error(403);
}

if ($_GET['forumid'] == 'all') {
    $Viewer->updateCatchup();
    header('Location: forums.php');
} else {
    // Insert a value for each topic
    $forum = new Gazelle\Forum((int)$_GET['forumid']);
    $forum->userCatchup($Viewer->id());
    header('Location: forums.php?action=viewforum&forumid=' . $_GET['forumid']);
}
