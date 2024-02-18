<?php

$statsUser = new Gazelle\Stats\Users();
$flow      = $statsUser->flow();

[$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements]
    = $statsUser->geodistribution();

echo $Twig->render('stats/user.twig', [
    'distribution' => [
        'browser'  => $statsUser->browserDistribution(),
        'class'    => $statsUser->userclassDistribution(),
        'platform' => $statsUser->platformDistribution(),
    ],
    'flow' => [
        'month' => array_keys($flow),
        'add'   => array_values(array_map(fn($m) => $m['new'], $flow)),
        'del'   => array_values(array_map(fn($m) => -$m['disabled'], $flow)),
        'net'   => array_values(array_map(fn($m) => $m['new'] - $m['disabled'], $flow)),
    ],
]);
