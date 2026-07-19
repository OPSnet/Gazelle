<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_blog')) {
    Error403::error();
}
authorize();

$blogMan = new Manager\Blog();
$blog = $blogMan->findById((int)($_GET['id'] ?? 0));
if (is_null($blog)) {
    Error404::error('You must provide an ID of a blog to delete');
}
$blog->remove();
$blogMan->flush();

header('Location: blog.php');
