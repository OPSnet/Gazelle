<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_reports')) {
    Error403::error();
}

$torMan        = new Manager\Torrent();
$reportMan     = new Manager\Torrent\Report($torMan);
$reportTypeMan = new Manager\Torrent\ReportType();
$requestMan    = new Manager\Request();
$userMan       = new Manager\User();
$search        = new Search\Torrent\Report($_GET['view'] ?? '', $_GET['id'] ?? '', $reportTypeMan, $userMan);

$paginator = new Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

$page = array_filter($search->page($reportMan, $paginator->limit(), $paginator->offset()),
    function ($report) {
        if ($report->torrent() === null) {
            if ($report->status() !== 'Resolved') {
                $report->resolve('Report already dealt with (torrent deleted)');
            }
            return false;
        }
        return true;
    }
);

echo $Twig->render('reportsv2/report_list.twig', [
        'linkbox'         => $paginator->linkbox(),
        'reports'         => $page,
        'report_man'      => $reportMan,
        'report_type_man' => $reportTypeMan,
        'request_man'     => $requestMan,
        'search'          => $search,
        'viewer'          => $Viewer,
]);
