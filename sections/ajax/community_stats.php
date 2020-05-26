<?php
if (!isset($_GET['userid']) || !is_number($_GET['userid'])) {
    json_die('failure');
}

$UserID = $_GET['userid'];
$User = new \Gazelle\User($UserID);
$UInfo = Users::user_info($UserID);

function check_paranoia_here($info, $setting) {
    return check_paranoia($setting, $info['Paranoia'], $info['Class'], $info['ID']);
}

$CommStats = [
    'leeching' => false,
    'seeding' => false,
    'snatched' => false,
    'usnatched' => false,
    'downloaded' => false,
    'udownloaded' => false,
    'seedingperc' => false,
];

if (check_paranoia_here($UInfo, 'seeding+') || check_paranoia_here($UInfo, 'leeching+')) {
    $peerCounts = $User->peerCounts();
    if (check_paranoia_here($UInfo, 'seeding+')) {
        $Seeding = $peerCounts['seeding'];
        $CommStats['seeding'] = number_format($Seeding);
    }
    if (check_paranoia_here($Uinfo, 'leeching+')) {
        $CommStats['leeching'] = $peerCounts['leeching'];
    }
}
if (check_paranoia_here($Uinfo, 'snatched+')) {
    list($Snatched, $UniqueSnatched) = $User->snatchCounts();
    $CommStats['snatched'] = number_format($Snatched);
    if (check_perms('site_view_torrent_snatchlist', $UInfo['Class'])) {
        $CommStats['usnatched'] = number_format($UniqueSnatched);
    }
    if (check_paranoia_here($Uinfo, 'seeding+') && check_paranoia_here($Uinfo, 'snatched+') && $UniqueSnatched > 0) {
        $CommStats['seedingperc'] = 100 * min(1, round($Seeding / $UniqueSnatched, 2));
    }
}
if ($UserID == $LoggedUser['ID'] || check_perms('site_view_torrent_snatchlist', $Class)) {
    list($NumDownloads, $UniqueDownloads) = $User->downloadCounts();
    $CommStats['downloaded'] = number_format($NumDownloads);
    $CommStats['udownloaded'] = number_format($UniqueDownloads);
}

json_die('success', $CommStats);
