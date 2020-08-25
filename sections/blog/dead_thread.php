<?php

authorize();

if (!check_perms('admin_manage_blog')) {
    error(403);
}

$blogId = (int)$_GET['id'];
if (!$blogId) {
    error('Please provide an ID for a blog post to remove the thread link from.');
}

$blogMan = new Gazelle\Manager\Blog;
$blogMan->removeThread($blogId);

header('Location: blog.php');
