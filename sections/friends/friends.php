<?php

$friend = new Gazelle\User\Friend($Viewer);
$paginator = new Gazelle\Util\Paginator(FRIENDS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($friend->total());

echo $Twig->render('user/friend.twig', [
    'list'      => $friend->page(new Gazelle\Manager\User, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
