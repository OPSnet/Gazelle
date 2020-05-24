<?php
authorize();

if (empty($_GET['id'])) {
    error('You must provide an ID of a blog to delete');
}

$BlogID = (int)$_GET['id'];
if ($BlogID > 0) {
    $DB->prepared_query("DELETE FROM blog WHERE ID = ?", $BlogID);
    $Cache->deleteMulti(['blog', 'feed_blog']);
}
header('Location: blog.php');
