<?php
if (!check_perms('users_mod')) {
    error(403);
}

if (!isset($_GET['userid'])) {
    $MainStats = Tracker::info() ?? [];
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
        $UserPeerStats = Tracker::user_peer_count($TorrentPass);
        $_GET['userid'] = $user->id();
    }
}

View::show_header('Tracker info');
echo $Twig->render('admin/tracker-info.twig', [
    'action'       => $_REQUEST['action'],
    'announce_key' => $TorrentPass,
    'main_stats'   => $MainStats ?? null,
    'peer_stats'   => $UserPeerStats ?? null,
    'user_id'      => $_GET['userid'] ?? null,
]);
View::show_footer();
