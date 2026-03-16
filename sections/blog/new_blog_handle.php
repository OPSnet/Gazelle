<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\NotificationType;

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
$notify = isset($_POST['notify']);

$thread = new Manager\ForumThread()->create(
    forum : new Forum(ANNOUNCEMENT_FORUM_ID),
    title : $title,
    body  : $body,
    user  : $Viewer,
);
$blog = new Manager\Blog()->create(
    title: $title,
    body: $body,
    thread: $thread,
    user: $Viewer,
);

if (isset($_POST['subscribe'])) {
    new User\Subscription($Viewer)->subscribe($thread);
}
if ($notify) {
    $notification = new Manager\Notification();
    $notification->push(
        $notification->pushableTokens(NotificationType::BLOG),
        "New blog article",
        $blog->title(),
        $blog->publicLocation()
    );
}
Util\Irc::sendMessage(IRC_CHAN, "New blog article: {$blog->title()}");

header('Location: blog.php');
