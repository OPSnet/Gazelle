<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

echo $Twig->render('admin/forum-category.twig', [
    'list'   => (new Gazelle\Manager\ForumCategory())->usageList(),
    'viewer' => $Viewer,
]);
