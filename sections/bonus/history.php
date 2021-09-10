<?php

if (isset($_GET['userid'])) {
    if (!$Viewer->permitted('admin_bp_history')) {
        error(403);
    }
    $userId = (int)$_GET['userid'];
    if (!$userId) {
        error(404);
    }
    $header = 'Bonus Points Spending History for ' . Users::format_username($userId);
    $whoSpent = Users::format_username($userId) . ' has spent';
} else {
    $userId = $Viewer->id();
    $header = 'Bonus Points Spending History';
    $whoSpent = 'You have spent';
}
$bonus = new Gazelle\Bonus(new Gazelle\User($userId));

$poolTotal = 0;
$poolSummary = $bonus->poolHistory();
foreach ($poolSummary as $p) {
    $poolTotal += $p['total'];
}

$summary = $bonus->summary();
$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($summary['nr']);

$adj = '';
if ($poolTotal && $summary['total']) {
    $total = $poolTotal + $summary['total'];
    if ($total > 500000) { $adj = 'very '; }
    elseif ($total >  1000000) { $adj = 'very, very '; }
    elseif ($total >  5000000) { $adj = 'extremely '; }
    elseif ($total > 10000000) { $adj = 'exceptionally '; }
}

echo $Twig->render('user/bonus-history.twig', [
    'history'      => $bonus->history($paginator->limit(), $paginator->offset()),
    'item'         => $bonus->purchaseHistoryByUser(),
    'summary'      => $summary,
    'pool_summary' => $poolSummary,
    'pool_total'   => $poolTotal,

    'adjective'    => $adj,
    'header'       => $header,
    'is_admin'     => $Viewer->permitted('admin_bp_history'),
    'now'          => time(),
    'paginator'    => $paginator,
    'self'         => $userId === $Viewer->id(),
    'user_id'      => $userId,
    'who_spent'    => $whoSpent,
]);
