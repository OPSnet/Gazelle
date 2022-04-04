<?php

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();

$body = trim($_POST['body']);
if (empty($body)) {
    error('The body of the blog article must not be empty');
}

$title = trim($_POST['title']);
if (empty($title)) {
    error('The title of the blog article must not be empty');
}

$blog = (new Gazelle\Manager\Blog)->findById((int)($_POST['blogid'] ?? 0));
if (is_null($blog)) {
    error(404);
}

$thread = match((int)($_POST['thread'] ?? -1)) {
    -1 => null,
     0 => (new Gazelle\Manager\ForumThread)->create(
        forumId: ANNOUNCEMENT_FORUM_ID,
        userId:  $Viewer->id(),
        title:   $title,
        body:    $body,
    ),
    default => (new Gazelle\Manager\ForumThread)->findById((int)$_POST['thread']),
};

if ($thread) {
    $blog->setUpdate('ThreadID', $thread->id());
}
$blog->setUpdate('Body', $body)
    ->setUpdate('Title', $title)
    ->setUpdate('Important', isset($_POST['important']) ? 1 : 0)
    ->modify();

if ($thread && isset($_POST['subscribe'])) {
    (new Gazelle\Subscription($Viewer))->subscribe($thread->id());
}

header('Location: blog.php');
