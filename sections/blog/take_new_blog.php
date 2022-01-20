<?php

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();

if (empty($_POST['title']) || empty($_POST['body'])) {
    error('You must have a title and body for the blog post.');
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

$blogMan = new Gazelle\Manager\Blog;
$blog = $blogMan->create([
    'title'     => trim($_POST['title']),
    'body'      => trim($_POST['body']),
    'important' => isset($_POST['important']) ? 1 : 0,
    'threadId'  => $threadId,
    'userId'    => $Viewer->id(),
]);

if (isset($_POST['subscribe']) && (int)$threadId) {
    (new Gazelle\Subscription($Viewer))->subscribe($threadId);
}
$notification = new Gazelle\Manager\Notification($Viewer->id());
$notification->push($notification->pushableUsers($Viewer->id()), $blog->title(), $blog->body(), SITE_URL . '/index.php', Gazelle\Manager\Notification::BLOG);

Gazelle\Util\Irc::sendRaw("PRIVMSG " . BOT_CHAN . " :New blog article: " . $blog->title());

header('Location: blog.php');
