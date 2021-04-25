<?php

if (!check_perms('admin_bp_history')) {
    error(403);
}

View::show_header('Bonus Points Statistics');

$bonus = new Gazelle\Stats\Bonus;
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
]);

View::show_footer();
