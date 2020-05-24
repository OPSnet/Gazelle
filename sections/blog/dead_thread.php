<?php

authorize();

if (!isset($_GET['id'])) {
    error('Please provide an ID for a blog post to remove the thread link from.');
}

G::$DB->prepared_query('UPDATE blog SET ThreadID = NULL WHERE ID = ? ', (int)$_GET['id']);
if (G::$DB->affected_rows() > 0) {
    $Cache->deleteMulti[('blog', 'feed_blog']);
}

header('Location: blog.php');
