<?php

if (!$Viewer->permitted('admin_manage_blog')) {
    error(403);
}
authorize();

$blogId = (int)($_GET['id'] ?? 0);
if (!$blogId) {
    error('You must provide an ID of a blog to delete');
}

(new Gazelle\Manager\Blog)->remove($blogId);

header('Location: blog.php');
