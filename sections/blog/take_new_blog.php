<?php

use \Gazelle\Manager\Notification;

authorize();

if (empty($_POST['title']) || empty($_POST['body'])) {
    error('You must have a title and body for the blog post.');
}

$ThreadID = !isset($_POST['thread']) || $_POST['thread'] === '' ? '' : max(0, intval($_POST['thread']));

if ($ThreadID > 0) {
    $DB->prepared_query("
        SELECT ForumID
        FROM forums_topics
        WHERE ID = ?", $ThreadID);
    if (!$DB->has_results()) {
        error('No such thread exists!');
    }
}
elseif ($ThreadID === '') {
    $forum = new \Gazelle\Forum(ANNOUNCEMENT_FORUM_ID);
    $ThreadID = $forum->addThread($LoggedUser['ID'], $_POST['title'], $_POST['body']);
    if ($ThreadID < 1) {
        error(0);
    }
}
else {
    $ThreadID = null;
}

$blog = new Gazelle\Manager\Blog;
$blog->create([
    'title'     => $_POST['title'],
    'body'      => $_POST['body'],
    'important' => isset($_POST['important']) ? 1 : 0,
    'threadId'  => $ThreadID,
    'userId'    => $LoggedUser['ID'],
]);

if (isset($_POST['subscribe']) && $ThreadID !== null && $ThreadID > 0) {
    $DB->prepared_query("
        INSERT IGNORE INTO users_subscriptions
        VALUES (?, ?)", $LoggedUser['ID'], $ThreadID);
    $Cache->delete_value('subscriptions_user_'.$LoggedUser['ID']);
}
$notification = new Notification($LoggedUser['ID']);
$notification->push($notification->pushableUsers(), $_POST['title'], $_POST['body'], site_url() . 'index.php', Notification::BLOG);

header('Location: blog.php');
