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
    $ThreadID = Misc::create_thread(ANNOUNCEMENT_FORUM_ID, G::$LoggedUser['ID'], $_POST['title'], $_POST['body']);
    if ($ThreadID < 1) {
        error(0);
    }
}
else {
    $ThreadID = null;
}

$Important = isset($_POST['important']) ? '1' : '0';
$DB->prepared_query("
    INSERT INTO blog
        (UserID, Title, Body, Time, ThreadID, Important)
    VALUES
        (?, ?, ?, ?, ?, ?)", G::$LoggedUser['ID'], $_POST['title'], $_POST['body'], sqltime(), $ThreadID, $Important);

$Cache->delete_value('blog');
if ($Important == '1') {
    $Cache->delete_value('blog_latest_id');
}
if (isset($_POST['subscribe']) && $ThreadID !== null && $ThreadID > 0) {
    $DB->prepared_query("
        INSERT IGNORE INTO users_subscriptions
        VALUES (?, ?)", G::$LoggedUser['ID'], $ThreadID);
    $Cache->delete_value('subscriptions_user_'.G::$LoggedUser['ID']);
}
Notification::send_push(Notification::get_push_enabled_users(), $_POST['title'], $_POST['body'], site_url() . 'index.php', Notification::BLOG);

header('Location: blog.php');
