<?php

authorize();

if (!isset($_GET['id'])) {
    error('Please provide an ID for a blog post to remove the thread link from.');
}

$ID = intval($_GET['id']);
G::$DB->prepared_query('UPDATE blog SET ThreadID = NULL WHERE ID = ? ', $ID);

if (G::$DB->affected_rows() > 0) {
    $Cache->delete_value('blog');
    $Cache->delete_value('feed_blog');
}

header('Location: blog.php');
