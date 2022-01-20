<?php

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();

$blogMan = new Gazelle\Manager\Blog;
$blog = $blogMan->findById((int)($_POST['blogid'] ?? 0));
if (!$blog) {
    error(404);
}
if (empty($_POST['body'])) {
    error('The body of the blog article must not be empty');
}
if (empty($_POST['title'])) {
    error('The title of the blog article must not be empty');
}

$threadId = !isset($_POST['thread']) || $_POST['thread'] === '' ? '' : max(0, (int)$_POST['thread']);
if ($threadId) {
    $forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
    if (!$forum) {
        error('No such thread exists!');
    }
} elseif ($threadId === '') {
    $forum = new Gazelle\Forum(ANNOUNCEMENT_FORUM_ID);
    $threadId = $forum->addThread($Viewer->id(), $_POST['title'], $_POST['body']);
    if (!$threadId) {
        error(0);
    }
} else {
    $threadId = null;
}

$blog->setUpdate('Body', trim($_POST['body']))
    ->setUpdate('Title', trim($_POST['title']))
    ->setUpdate('ThreadID', $threadId)
    ->setUpdate('Important', isset($_POST['important']) ? 1 : 0)
    ->modify();

if (isset($_POST['subscribe']) && (int)$threadId) {
    (new Gazelle\Subscription($Viewer))->subscribe($threadId);
}

header('Location: blog.php');
