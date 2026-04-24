<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

/*
 * This is the AJAX page that gets called from the JavaScript
 * function NewReport().
 */

if (!$Viewer->permitted('admin_reports')) {
    Error403::error();
}

$torMan    = new Manager\Torrent();
$reportMan = new Manager\Torrent\Report($torMan);

$report = $reportMan->findNewest();
if (!$report) {
    exit;
}
$torrent = $report->torrent();
if (is_null($torrent)) {
    if ($report->status() !== 'Resolved') {
        $report->resolve('Report already dealt with (torrent deleted)');
    }
    echo $Twig->render('reportsv2/deleted.twig', [
        'report'  => $report,
    ]);
    exit;
}

$report->claim($Viewer);
echo $Twig->render('reportsv2/report.twig', [
    'category_list' => new Manager\Torrent\ReportType()->categoryList($torrent->group()->categoryId()),
    'report'        => $report,
    'request_list'  => new Manager\Request()->findByTorrentReported($torrent),
    'torrent'       => $torrent,
    'other'         => [
        'group'    => $reportMan->totalReportsTGroup($torrent->group()) - 1,
        'uploader' => $reportMan->totalReportsUploader($torrent->uploader()) - 1,
        'torrent'  => $reportMan->totalReportsTorrent($torrent) - 1,
    ],
    'viewer' => $Viewer,
]);
