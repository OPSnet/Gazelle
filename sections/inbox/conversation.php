<?php

$pm = (new Gazelle\Manager\PM($Viewer))->findById((int)$_GET['id']);
if (is_null($pm)) {
    error(404);
}

$pm->markRead();
$postTotal = $pm->postTotal();
$paginator = new Gazelle\Util\Paginator(POSTS_PER_PAGE, (int)($_GET['page'] ?? ceil($postTotal / POSTS_PER_PAGE)));
$paginator->setTotal($postTotal);

echo $Twig->render('inbox/conversation.twig', [
    'inbox'      => (new Gazelle\Inbox($Viewer))->setFolder($_GET['section'] ?? 'inbox'),
    'paginator'  => $paginator,
    'pm'         => $pm,
    'post_list'  => $pm->postList($paginator->limit(), $paginator->offset()),
    'staff_list' => (new Gazelle\Manager\User)->staffPMList(),
    'viewer'     => $Viewer,
]);
