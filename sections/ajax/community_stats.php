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
    [$Snatched, $UniqueSnatched] = $user->snatchCounts();
    $CommStats['snatched'] = number_format($Snatched);
    if ($user->permitted('site_view_torrent_snatchlist')) {
        $CommStats['usnatched'] = number_format($UniqueSnatched);
    }
    if ($seedingVisible && $user->propertyVisible($Viewer, 'snatched+')) {
        $CommStats['seedingperc'] = 100 * min(1, round($Seeding / $UniqueSnatched, 2));
    }
}
if ($user->id() == $Viewer->id() || $Viewer->permitted('site_view_torrent_snatchlist')) {
    [$NumDownloads, $UniqueDownloads] = $user->downloadCounts();
    $CommStats['downloaded'] = number_format($NumDownloads);
    $CommStats['udownloaded'] = number_format($UniqueDownloads);
}

json_die('success', $CommStats);
