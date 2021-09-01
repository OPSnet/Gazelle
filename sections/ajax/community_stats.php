<?php

$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    json_die('failure');
}

$CommStats = [
    'leeching'    => false,
    'seeding'     => false,
    'snatched'    => false,
    'usnatched'   => false,
    'downloaded'  => false,
    'udownloaded' => false,
    'seedingperc' => false,
];

$leechingVisible = $user->propertyVisible($Viewer, 'leeching+');
$seedingVisible = $user->propertyVisible($Viewer, 'seeding+');
if ($leechingVisible || $seedingVisible) {
    $peerCounts = $user->peerCounts();
    if ($leechingVisible) {
        $CommStats['leeching'] = number_format($peerCounts['leeching']);
    }
    if ($seedingVisible) {
        $Seeding = $peerCounts['seeding'];
        $CommStats['seeding'] = number_format($Seeding);
    }
}
if ($user->propertyVisible($Viewer, 'snatched+')) {
    $CommStats['snatched'] = number_format($user->stats()->snatchTotal());
    $UniqueSnatched = $user->stats()->snatchUnique();
    if ($user->permitted('site_view_torrent_snatchlist')) {
        $CommStats['usnatched'] = number_format($UniqueSnatched);
    }
    if ($seedingVisible && $user->propertyVisible($Viewer, 'snatched+')) {
        $CommStats['seedingperc'] = $UniqueSnatched ? round(100 * min(1, $Seeding / $UniqueSnatched), 2) : 0;
    }
}
if ($user->id() == $Viewer->id() || $user->propertyVisible($Viewer, 'download') || $Viewer->permitted('site_view_torrent_snatchlist')) {
    $CommStats['downloaded'] = number_format($user->stats()->downloadTotal());
    $CommStats['udownloaded'] = number_format($user->stats()->downloadUnique());
}

json_die('success', $CommStats);
