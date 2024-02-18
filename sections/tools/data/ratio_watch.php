<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

$userMan = new Gazelle\Manager\User();
$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($userMan->totalRatioWatchUsers());

echo $Twig->render('admin/ratio-watch.twig', [
    'paginator'      => $paginator,
    'total_disabled' => $userMan->totalBannedForRatio(),
    'users'          => $userMan->ratioWatchUsers($paginator->limit(), $paginator->offset()),
]);
