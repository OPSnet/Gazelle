<?php
authorize();

if (empty($_POST['blogid']) || empty($_POST['body']) || empty($_POST['title'])) {
    error('You must provide a blog id, title, and body when editing a blog entry.');
}

$BlogID = intval($_POST['blogid']);
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
    $ThreadID = $forum->addThread(G::$LoggedUser['ID'], $_POST['title'], $_POST['body']);
    if ($ThreadID < 1) {
        error(0);
    }
}
else {
    $ThreadID = null;
}

$Important = isset($_POST['important']) ? '1' : '0';

if ($BlogID > 0) {
    $DB->prepared_query("
        UPDATE blog
        SET
            Title = ?,
            Body = ?,
            ThreadID = ?,
            Important = ?
        WHERE ID = ?", $_POST['title'], $_POST['body'], $ThreadID, $Important, $BlogID);
    $Cache->delete_value('blog');
    $Cache->delete_value('feed_blog');
    if ($Important == '1') {
        $Cache->delete_value('blog_latest_id');
    }
    if (isset($_POST['subscribe']) && $ThreadID !== null && $ThreadID > 0) {
        $DB->prepared_query("
        INSERT IGNORE INTO users_subscriptions
        VALUES (?, ?)", G::$LoggedUser['ID'], $ThreadID);
        $Cache->delete_value('subscriptions_user_'.G::$LoggedUser['ID']);
    }
}

header('Location: blog.php');
