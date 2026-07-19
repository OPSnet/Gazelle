<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_bp_history')) {
    Error403::error();
}

$bonus = new Stats\Bonus();
$day = [];
$week = [];
$month = [];
foreach (range(0, 6) as $n) {
    $day[] = $bonus->accrualRange('DAY', $n, 1);
    $week[] = $bonus->accrualRange('WEEK', $n, 1);
    $month[] = $bonus->accrualRange('MONTH', $n, 1);
}

echo $Twig->render('admin/bonus-stats.twig', [
    'bonus' => $bonus,
    'day'   => $day,
    'week'  => $week,
    'month' => $month,
    'fl'    => new Stats\Users()->stockpileTokenList(10),
]);
