<?php

if (!$Viewer->permitted('admin_manage_contest')) {
    error(403);
}

$contestMan = new Gazelle\Manager\Contest;
$create     = isset($_GET['action']) && $_GET['action'] === 'create';
$saved      = false;

if (!empty($_POST['cid'])) {
    authorize();
    $contest = new Gazelle\Contest((int)$_POST['cid']);
    $contest->save($_POST);
    $saved = true;
} elseif (!empty($_POST['new'])) {
    authorize();
    $contest = $contestMan->create($_POST);
} elseif (!empty($_GET['id'])) {
    $contest = new Gazelle\Contest((int)$_GET['id']);
} elseif (!$create) {
    $contest = $contestMan->currentContest();
} else {
    $contest = null;
}

echo $Twig->render('contest/admin.twig', [
    'contest'    => $contest,
    'create'     => $create,
    'intro'      => new Gazelle\Util\Textarea('description', $contest?->description() ?? '', 60, 8),
    'list'       => $contestMan->contestList(),
    'saved'      => $saved,
    'type'       => $contestMan->contestTypes(),
    'user_count' => (new \Gazelle\Stats\Users)->enabledUserTotal(),
    'viewer'     => $Viewer,
]);
