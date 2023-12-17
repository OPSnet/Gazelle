<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tracker = new Gazelle\Tracker;
$stats   = false;
$user    = null;

if (isset($_GET['userid'])) {
    $user = (new Gazelle\Manager\User)->find($_GET['userid']);
    if ($user) {
        $stats = $tracker->user_peer_count($user);
        $_GET['userid'] = $user->id(); // change @user to id
    }
}

echo $Twig->render('admin/tracker-info.twig', [
    'action'       => $_REQUEST['action'],
    'main_stats'   => $tracker->info(),
    'peer_stats'   => $stats,
    'user_id'      => $_GET['userid'] ?? null,
    'user'         => $user,
]);
