<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tracker = new Gazelle\Tracker;
if (!isset($_GET['userid'])) {
    $MainStats = $tracker->info();
    $main = [];
    foreach ($MainStats as $Key => $Value) {
        if (is_numeric($Value)) {
            if (substr($Key, 0, 6) === "bytes ") {
                $Value = Format::get_size($Value);
                $Key = substr($Key, 6);
            } else {
                $Value = number_format($Value);
            }
        }
        $main[$Key] = $Value;
    }
} else {
    $user = (new Gazelle\Manager\User)->find($_GET['userid']);
    if (!$user) {
        $UserPeerStats = false;
    } else {
    echo $user->username();
        $TorrentPass = $user->announceKey();
        $UserPeerStats = $tracker->user_peer_count($TorrentPass);
        $_GET['userid'] = $user->id();
    }
}

echo $Twig->render('admin/tracker-info.twig', [
    'action'       => $_REQUEST['action'],
    'announce_key' => $TorrentPass,
    'main_stats'   => $MainStats ?? null,
    'peer_stats'   => $UserPeerStats ?? null,
    'user_id'      => $_GET['userid'] ?? null,
]);
