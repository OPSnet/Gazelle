<?php
authorize();

if (!check_perms('admin_manage_blog')) {
    error(403);
}

$blogId = (int)($_GET['id'] ?? 0);
if (!$blogId) {
    error('You must provide an ID of a blog to delete');
}
$blogMan = new Gazelle\Manager\Blog;
$blogMan->remove($blogId);

header('Location: blog.php');
