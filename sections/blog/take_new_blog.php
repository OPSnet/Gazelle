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

$blog = (new Gazelle\Manager\Blog)->create([
    'title'     => $title,
    'body'      => $body,
    'important' => isset($_POST['important']) ? 1 : 0,
    'threadId'  => $thread?->id(),
    'userId'    => $Viewer->id(),
]);

if ($thread && isset($_POST['subscribe'])) {
    (new Gazelle\Subscription($Viewer))->subscribe($thread->id());
}
$notification = new Gazelle\Manager\Notification($Viewer->id());
$notification->push($notification->pushableUsers($Viewer->id()), $blog->title(), $blog->body(), SITE_URL . '/index.php', Gazelle\Manager\Notification::BLOG);

Gazelle\Util\Irc::sendRaw("PRIVMSG " . BOT_CHAN . " :New blog article: " . $blog->title());

header('Location: blog.php');
