<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('admin_bp_history')) {
        Error403::error();
    }
    $user = new Manager\User()->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        Error404::error();
    }
}

$bonus = new User\Bonus($user);
$total = $bonus->userTotals();
$paginator = new Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($total['total_torrents']);

echo $Twig->render('user/bonus.twig', [
    'heading'   => $bonus->heading(),
    'list'      => $bonus->seedList($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'total'     => $total,
    'user'      => $user,
    'viewer'    => $Viewer,
]);
