<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

/*
 * This page is to outline all of the views built into reports v2.
 * It's used as the main page as it also lists the current reports by type
 * and the current in-progress reports by staff member.
 * All the different views are self explanatory by their names.
 */
if (!$Viewer->permitted('admin_reports')) {
    Error403::error();
}

$reportMan = new Manager\Torrent\Report(new Manager\Torrent());

echo $Twig->render('reportsv2/summary.twig', [
    'in_progress' => $reportMan->inProgressSummary(),
    'new'         => $reportMan->newSummary(),
    'resolved'    => [
        'day'   => $reportMan->resolvedLastDay(),
        'week'  => $reportMan->resolvedLastWeek(),
        'month' => $reportMan->resolvedLastMonth(),
        'total' => $reportMan->resolvedSummary(),
    ],
    'viewer' => $Viewer,
]);
