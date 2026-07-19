<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_forums')) {
    Error403::error();
}

echo $Twig->render('admin/forum-management.twig', [
    'category'   => new Manager\ForumCategory()->forumCategoryList(),
    'class_list' => new Manager\User()->classList(),
    'toc'        => new Manager\Forum()->tableOfContents($Viewer),
    'viewer'     => $Viewer,
]);
