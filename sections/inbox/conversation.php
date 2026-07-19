<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$pm = new Manager\PM($Viewer)->findById((int)($_GET['id'] ?? 0));
if (is_null($pm)) {
    Error404::error();
}

$pm->markRead();
$postTotal = $pm->postTotal();
$paginator = new Util\Paginator(POSTS_PER_PAGE, (int)($_GET['page'] ?? ceil($postTotal / POSTS_PER_PAGE)));
$paginator->setTotal($postTotal);

echo $Twig->render('inbox/conversation.twig', [
    'body'       => new Util\Textarea('body', '', 90, 10),
    'inbox'      => $Viewer->inbox()->setFolder($_GET['section'] ?? 'inbox'),
    'paginator'  => $paginator,
    'pm'         => $pm,
    'post_list'  => $pm->postList($paginator->limit(), $paginator->offset()),
    'staff_list' => new Manager\User()->staffPMList(),
    'viewer'     => $Viewer,
]);
