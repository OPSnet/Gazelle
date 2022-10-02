<?php

$statsUser = new Gazelle\Stats\Users;
$flow      = $statsUser->flow();

[$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements]
    = $statsUser->geoDistribution();

echo $Twig->render('stats/user.twig', [
    'distribution' => [
        'browser'  => $statsUser->browserDistribution(),
        'class'    => $statsUser->classDistribution(),
        'platform' => $statsUser->platformDistribution(),
    ],
    'flow' => [
        'month' => array_keys($flow),
        'add'   => array_values(array_map(fn($m) => $m['Joined'], $flow)),
        'del'   => array_values(array_map(fn($m) => -$m['Disabled'], $flow)),
        'net'   => array_values(array_map(fn($m) => $m['Joined'] - $m['Disabled'], $flow)),
    ],
]);
