<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_blog')) {
    Error403::error();
}
authorize();

$body = trim($_POST['body']);
if (empty($body)) {
    Error400::error('The body of the blog article must not be empty');
}
$title = trim($_POST['title']);
if (empty($title)) {
    Error400::error('The title of the blog article must not be empty');
}
$blog = new Manager\Blog()->findById((int)($_POST['blogid'] ?? 0));
if (is_null($blog)) {
    Error404::error();
}

$manager = new Manager\ForumThread();
$thread = match ((int)($_POST['thread'] ?? -1)) {
    -1 => null,
     0 => $manager->create(
        forum: new Forum(ANNOUNCEMENT_FORUM_ID),
        user:  $Viewer,
        title: $title,
        body:  $body,
    ),
    default => $manager->findById((int)$_POST['thread']),
};

if ($thread) {
    $blog->setField('id_thread', $thread->id());
}
$blog->setField('body', $body)
    ->setField('title', $title)
    ->modify();

if ($thread && isset($_POST['subscribe'])) {
    new User\Subscription($Viewer)->subscribe($thread);
}

header('Location: blog.php');
