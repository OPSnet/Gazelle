<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tracker       = new Gazelle\Tracker;
$UserPeerStats = false;
if (isset($_GET['userid'])) {
    $user = (new Gazelle\Manager\User)->find($_GET['userid']);
    if ($user) {
        $TorrentPass = $user->announceKey();
        $UserPeerStats = $tracker->user_peer_count($TorrentPass);
        $_GET['userid'] = $user->id();
    }
} else {
    $MainStats = $tracker->info();
    $main = [];
    foreach ($MainStats as $Key => $Value) {
        if (is_numeric($Value)) {
            if (str_starts_with($Key, "bytes ")) {
                $Key   = substr($Key, 6);
                $Value = byte_format((int)$Value);
            } else {
                $Value = number_format((float)$Value);
            }
        }
        $main[$Key] = $Value;
    }
}

echo $Twig->render('admin/tracker-info.twig', [
    'action'       => $_REQUEST['action'],
    'announce_key' => $TorrentPass ?? 'none',
    'main_stats'   => $MainStats ?? null,
    'peer_stats'   => $UserPeerStats ,
    'user_id'      => $_GET['userid'] ?? null,
]);
