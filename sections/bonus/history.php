<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!isset($_GET['userid'])) {
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

$bonus       = new User\Bonus($user);
$summary     = $bonus->summary();
$poolSummary = $bonus->poolHistory();
$paginator   = new Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($summary['nr']);

echo $Twig->render('bonus/history.twig', [
    'exchange'     => $bonus->tokenExchange(),
    'history'      => $bonus->history($paginator->limit(), $paginator->offset()),
    'item'         => $bonus->purchaseHistory(),
    'summary'      => $summary,
    'pool_summary' => $poolSummary,
    'pool_total'   => array_reduce(
        $poolSummary,
        fn (?float $sum, array $s): float => $sum + $s['total']
    ),
    'paginator'    => $paginator,
    'user'         => $user,
    'viewer'       => $Viewer,
]);
