<?php
authorize();

if (!check_perms('admin_manage_blog')) {
    error(403);
}

if (empty($_POST['blogid']) || empty($_POST['body']) || empty($_POST['title'])) {
    error('You must provide a blog id, title, and body when editing a blog entry.');
}

$ThreadID = !isset($_POST['thread']) || $_POST['thread'] === '' ? '' : max(0, intval($_POST['thread']));

if ($ThreadID > 0) {
    if (!$DB->scalar("
        SELECT 1 FROM forums_topics WHERE ID = ?
        ", $ThreadID
    )) {
        error('No such thread exists!');
    }
}
elseif ($ThreadID === '') {
    $forum = new Gazelle\Forum(ANNOUNCEMENT_FORUM_ID);
    $ThreadID = $forum->addThread($Viewer->id(), $_POST['title'], $_POST['body']);
    if ($ThreadID < 1) {
        error(0);
    }
}
else {
    $ThreadID = null;
}

$BlogID = (int)$_POST['blogid'];
if ($BlogID) {
    $blogMan = new Gazelle\Manager\Blog;
    $blogMan->modify([
        'id'        => $BlogID,
        'title'     => $_POST['title'],
        'body'      => $_POST['body'],
        'important' => isset($_POST['important']) ? 1 : 0,
        'threadId'  => $ThreadID,
    ]);
    if (isset($_POST['subscribe']) && $ThreadID !== null && $ThreadID > 0) {
        $subMan = new Gazelle\Manager\Subscription($Viewer->id());
        $subMan->subscribe($ThreadID);
    }
}

header('Location: blog.php');
