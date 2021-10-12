<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

$forumMan = new Gazelle\Manager\Forum;

echo $Twig->render('admin/forum-management.twig', [
    'auth'       => $Viewer->auth(),
    'category'   => $forumMan->categoryList(),
    'class_list' => (new Gazelle\Manager\User)->classList(),
    'toc'        => $forumMan->tableOfContentsMain(),
]);
