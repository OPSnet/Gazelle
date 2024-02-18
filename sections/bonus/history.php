<?php

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('admin_bp_history')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User())->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
}

$bonus       = new Gazelle\User\Bonus($user);
$summary     = $bonus->summary();
$poolSummary = $bonus->poolHistory();
$paginator   = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($summary['nr']);

echo $Twig->render('user/bonus-history.twig', [
    'history'      => $bonus->history($paginator->limit(), $paginator->offset()),
    'item'         => $bonus->purchaseHistory(),
    'summary'      => $summary,
    'pool_summary' => $poolSummary,
    'pool_total'   => array_reduce($poolSummary, fn ($sum = 0, array $s = []) => $sum + $s['total']),
    'paginator'    => $paginator,
    'user'         => $user,
    'viewer'       => $Viewer,
]);
