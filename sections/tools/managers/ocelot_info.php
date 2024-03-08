<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tracker = new Gazelle\Tracker();
$stats   = false;
$user    = null;
$info    = $tracker->info();

if (isset($_GET['userid'])) {
    $user = (new Gazelle\Manager\User())->find($_GET['userid']);
    if ($user) {
        $stats = $tracker->userReport($user);
        $_GET['userid'] = $user->id(); // change @user to id
    }
}

$reannounceTotal = 0;
$reannounced = 0;
if (isset($_POST['tlist'])) {
    $torMan = new Gazelle\Manager\Torrent();
    foreach (extract_torrent_id($_POST['tlist']) as $id) {
        $reannounceTotal++;
        $torrent = $torMan->findById($id);
        if ($torrent && !$torrent->isDeleted()) {
            $reannounced += (int)$tracker->addTorrent($torrent);
        }
    }
}

$dirty = false;
if (isset($_POST['interval'])) {
    authorize();
    $interval = (int)$_POST['interval'];
    if ($interval != $info['announce interval']['value']) {
        if ($interval < 600) {
            error("Cowardly refusing to lower the announce interval below five minutes");
        }
        $tracker->modifyAnnounceInterval($interval);
        $dirty = true;
    }
}

if (isset($_POST['jitter'])) {
    authorize();
    $jitter = (int)$_POST['jitter'];
    if ($jitter != $info['announce jitter']['value']) {
        if ($jitter < 0 || $jitter >= 3600) {
            error("Cowardly refusing to set the jitter to an absurd value");
        }
        $tracker->modifyAnnounceJitter($jitter);
        $dirty = true;
    }
}

if ($dirty) {
    $info = $tracker->info();
}

echo $Twig->render('admin/tracker-info.twig', [
    'action'       => $_REQUEST['action'],
    'delay'        => $tracker->delay(),
    'main_stats'   => $info,
    'mem_stats'    => ($_GET['status'] ?? '' == 'memory') ? $tracker->infoMemoryAlloc() : null,
    'reannounce'   => [
        'active'  => isset($_POST['tlist']),
        'total'   => $reannounceTotal,
        'success' => $reannounced,
    ],
    'user_stats'   => $stats,
    'user_id'      => $_GET['userid'] ?? null,
    'user'         => $user,
    'viewer'       => $Viewer,
]);
