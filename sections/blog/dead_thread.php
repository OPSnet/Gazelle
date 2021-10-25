<?php

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();

$blogId = (int)$_GET['id'];
if (!$blogId) {
    error('Please provide an ID for a blog post to remove the thread link from.');
}

(new Gazelle\Manager\Blog)->removeThread($blogId);

header('Location: blog.php');
