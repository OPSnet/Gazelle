<?php

if (isset($_GET['userid'])) {
    if (!check_perms('admin_bp_history')) {
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

$poolTotal = 0;
$poolSummary = $Bonus->userPoolHistory($userId);
foreach ($poolSummary as $p) {
    $poolTotal += $p['total'];
}

$summary = $Bonus->userSummary($userId);
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

View::show_header('Bonus Points Purchase History', ['js' => 'bonus']);
echo $Twig->render('user/bonus-history.twig', [
    'history'      => $Bonus->userHistory($userId, $paginator->limit(), $paginator->offset()),
    'item'         => $Bonus->purchaseHistoryByUser($userId),
    'summary'      => $summary,
    'pool_summary' => $poolSummary,
    'pool_total'   => $poolTotal,

    'adjective'    => $adj,
    'header'       => $header,
    'is_admin'     => check_perms('admin_bp_history'),
    'now'          => time(),
    'paginator'    => $paginator,
    'self'         => $userId === $Viewer->id(),
    'user_id'      => $userId,
    'who_spent'    => $whoSpent,
]);
View::show_footer();
