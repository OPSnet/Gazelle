<?php

use Gazelle\Util\Irc;

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();


if (empty($_POST['title']) || empty($_POST['body'])) {
    error('You must have a title and body for the blog post.');
}

$ThreadID = !isset($_POST['thread']) || $_POST['thread'] === '' ? '' : max(0, intval($_POST['thread']));

if ($ThreadID > 0) {
    if (!$DB->scalar("SELECT ForumID FROM forums_topics WHERE ID = ?", $ThreadID)) {
        error('No such thread exists!');
    }
} elseif ($ThreadID === '') {
    $forum = new \Gazelle\Forum(ANNOUNCEMENT_FORUM_ID);
    $ThreadID = $forum->addThread($Viewer->id(), $_POST['title'], $_POST['body']);
    if ($ThreadID < 1) {
        error(0);
    }
} else {
    $ThreadID = null;
}

$blogMan = new Gazelle\Manager\Blog;
$blog = $blogMan->create([
    'title'     => trim($_POST['title']),
    'body'      => trim($_POST['body']),
    'important' => isset($_POST['important']) ? 1 : 0,
    'threadId'  => $ThreadID,
    'userId'    => $Viewer->id(),
]);

if (isset($_POST['subscribe']) && $ThreadID !== null && $ThreadID > 0) {
    (new Gazelle\Manager\Subscription($Viewer->id()))->subscribe($ThreadID);
}
$notification = new Gazelle\Manager\Notification($Viewer->id());
$notification->push($notification->pushableUsers(), $blog->title(), $blog->body(), SITE_URL . '/index.php', Notification::BLOG);

Irc::sendRaw("PRIVMSG " . BOT_CHAN . " :New blog article: " . $blog->title());

header('Location: blog.php');
