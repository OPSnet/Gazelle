<?php
/** @phpstan-var \Twig\Environment $Twig */

$statsUser = new Gazelle\Stats\Users();
$flow      = $statsUser->flow();

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
    'geodist' => [
        'iso'      => ISO3166_2(),
        'list'     => $statsUser->geodistributionChart($Viewer),
        'topology' => worldTopology(),
    ],
]);
