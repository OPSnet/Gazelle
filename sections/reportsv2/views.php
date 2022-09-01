<?php
/*
 * This page is to outline all of the views built into reports v2.
 * It's used as the main page as it also lists the current reports by type
 * and the current in-progress reports by staff member.
 * All the different views are self explanatory by their names.
 */
if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

$reportMan = new Gazelle\Manager\ReportV2;

echo $Twig->render('reportsv2/summary.twig', [
    'in_progress' => $reportMan->inProgressSummary(),
    'new'         => $reportMan->newSummary(),
    'resolved'    => [
        'day'   => $reportMan->resolvedLastMonth(),
        'week'  => $reportMan->resolvedLastMonth(),
        'month' => $reportMan->resolvedLastMonth(),
        'total' => $reportMan->resolvedSummary(),
    ],
    'viewer'      => $Viewer,
]);
