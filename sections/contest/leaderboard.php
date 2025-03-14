<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

/** @var Gazelle\Manager\Contest $contestMan */

$prior = $contestMan->priorContests();
if (!empty($_POST['leaderboard'])) {
    $contest = new Gazelle\Contest((int)$_POST['leaderboard']);
} elseif (!empty($_GET['id'])) {
    $contest = new Gazelle\Contest((int)$_GET['id']);
} else {
    $contest = $contestMan->currentContest();
    if (is_null($contest)) {
        $contest = end($prior);
    }
}

$paginator = new Gazelle\Util\Paginator(CONTEST_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($contest->totalUsers());
$isRequestFill = $contest instanceof \Gazelle\Contest\RequestFill;

echo $Twig->render('contest/leaderboard.twig', [
    'action'        => $isRequestFill ? 'fill' : 'upload',
    'action_header' => $isRequestFill ? 'requests have been filled' : 'torrents have been uploaded',
    'score_header'  => $isRequestFill ? 'Requests Filled' : 'Perfect FLACs',
    'contest'       => $contest,
    'prior'         => $prior,
    'paginator'     => $paginator,
    'title'         => empty($contest) ? 'Leaderboard' : $contest->name() . ' Leaderboard',
    'viewer'        => $Viewer,
]);
