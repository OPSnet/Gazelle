<?php

$paginator = new Gazelle\Util\Paginator(FRIENDS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($Viewer->totalFriends());

echo $Twig->render('user/friend.twig', [
    'list'      => $Viewer->friendList($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
