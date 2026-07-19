<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_view_flow')) {
    Error403::error();
}

$userMan = new Manager\User();
$paginator = new Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($userMan->totalRatioWatchUsers());

echo $Twig->render('admin/ratio-watch.twig', [
    'paginator'      => $paginator,
    'total_disabled' => $userMan->totalBannedForRatio(),
    'users'          => $userMan->ratioWatchUsers($paginator->limit(), $paginator->offset()),
]);
