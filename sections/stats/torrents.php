<?php

/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$statsTor = new Stats\Torrent();
$flow = $statsTor->flow();

echo $Twig->render('stats/torrent.twig', [
    'flow' => [
        'month' => array_values(array_map(fn ($m) => $m['month'], $flow)),
        'add'   => array_values(array_map(fn ($m) => $m['t_add'], $flow)),
        'del'   => array_values(array_map(fn ($m) => $m['t_del'], $flow)),
        'net'   => array_values(array_map(fn ($m) => $m['t_net'], $flow)),
    ],
    'category' => $statsTor->categoryTotal(),
]);
