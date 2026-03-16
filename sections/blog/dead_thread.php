<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_blog')) {
    Error403::error();
}
authorize();

$blog = new Manager\Blog()->findById((int)($_GET['id'] ?? 0));
if (is_null($blog)) {
    Error404::error(
        'Please provide the ID of a blog post from which to remove the thread link.'
    );
}
$blog->setField('id_thread', null)->modify();

header('Location: blog.php');
