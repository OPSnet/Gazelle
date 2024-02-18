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

$blog = (new Gazelle\Manager\Blog())->findById((int)($_POST['blogid'] ?? 0));
if (is_null($blog)) {
    error(404);
}

$manager = new Gazelle\Manager\ForumThread();
$thread = match ((int)($_POST['thread'] ?? -1)) {
    -1 => null,
     0 => $manager->create(
        forum: new Gazelle\Forum(ANNOUNCEMENT_FORUM_ID),
        user:  $Viewer,
        title: $title,
        body:  $body,
    ),
    default => $manager->findById((int)$_POST['thread']),
};

if ($thread) {
    $blog->setField('ThreadID', $thread->id());
}
$blog->setField('Body', $body)
    ->setField('Title', $title)
    ->setField('Important', isset($_POST['important']) ? 1 : 0)
    ->modify();

if ($thread && isset($_POST['subscribe'])) {
    (new Gazelle\User\Subscription($Viewer))->subscribe($thread->id());
}

header('Location: blog.php');
