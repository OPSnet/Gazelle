<?php

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();

$blogMan = new Gazelle\Manager\Blog;
$blog = $blogMan->findById((int)($_GET['id'] ?? 0));
if (is_null($blog)) {
    error('You must provide an ID of a blog to delete');
}
$blog->remove();
$blogMan->flush();

header('Location: blog.php');
