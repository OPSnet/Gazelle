<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

echo $Twig->render('admin/forum-management.twig', [
    'auth'       => $Viewer->auth(),
    'category'   => (new Gazelle\Manager\ForumCategory())->forumCategoryList(),
    'class_list' => (new Gazelle\Manager\User())->classList(),
    'toc'        => (new Gazelle\Manager\Forum())->tableOfContents($Viewer),
]);
