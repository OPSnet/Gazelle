<?php

/**
 * @phpstan-var \Twig\Environment $Twig
 * @phpstan-var \Gazelle\User $Viewer
 */

declare(strict_types=1);

namespace Gazelle;

$siteLog = new Manager\SiteLog();
$page    = (int)($_GET['page'] ?? 1);
if (!$Viewer->permitted('site_view_full_log')) {
    $page = min($page, MAX_LOG_DEPTH / LOG_ENTRIES_PER_PAGE);
}

$search    = $_GET['search'] ?? '';
$paginator = new Util\Paginator(LOG_ENTRIES_PER_PAGE, $page);
$paginator->setTotal($siteLog->total($search));

echo $Twig->render('sitelog.twig', [
    'search'    => $search,
    'paginator' => $paginator,
    'page'      => $siteLog->page(
        LOG_ENTRIES_PER_PAGE,
        $paginator->offset(),
        $search,
    ),
]);
