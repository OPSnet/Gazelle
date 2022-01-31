<?php

$page = !empty($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * ($page-1);

$heading = new \Gazelle\Util\SortableTableHeader('hourlypoints', [
    'size'          => ['dbColumn' => 'size',           'defaultSort' => 'desc', 'text' => 'Size'],
    'seeders'       => ['dbColumn' => 'seeders',        'defaultSort' => 'desc', 'text' => 'Seeders'],
    'seedtime'      => ['dbColumn' => 'seed_time',      'defaultSort' => 'desc', 'text' => 'Duration'],
    'hourlypoints'  => ['dbColumn' => 'hourly_points',  'defaultSort' => 'desc', 'text' => 'BP/hour'],
    'dailypoints'   => ['dbColumn' => 'daily_points',   'defaultSort' => 'desc', 'text' => 'BP/day'],
    'weeklypoints'  => ['dbColumn' => 'weekly_points',  'defaultSort' => 'desc', 'text' => 'BP/week'],
    'monthlypoints' => ['dbColumn' => 'monthly_points', 'defaultSort' => 'desc', 'text' => 'BP/month'],
    'yearlypoints'  => ['dbColumn' => 'yearly_points',  'defaultSort' => 'desc', 'text' => 'BP/year'],
    'pointspergb'   => ['dbColumn' => 'points_per_gb',  'defaultSort' => 'desc', 'text' => 'BP/GB/year'],
]);

$userMan = new Gazelle\Manager\User;
if (empty($_GET['userid'])) {
    $user = $Viewer;
    $ownProfile = true;
} else {
    if (!$Viewer->permitted('admin_bp_history')) {
        error(403);
    }
    $user = $userMan->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
    $ownProfile = false;
}

$bonus = new Gazelle\User\Bonus($user);
$total = $bonus->userTotals();
$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($total['total_torrents']);

echo $Twig->render('user/bonus.twig', [
    'heading'   => $heading,
    'list'      => $bonus->seedList($heading->getOrderBy(), $heading->getOrderDir(), $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'title'     => $ownProfile ? 'Your Bonus Points Rate' : ($user->username() . "'s Bonus Point Rate"),
    'total'     => $total,
    'user'      => $user,
    'viewer'    => $Viewer,
]);
