<?php
/** @phpstan-var \Twig\Environment $Twig */

$siteLog = new Gazelle\Manager\SiteLog(new Gazelle\Manager\User());
$page    = (int)($_GET['page'] ?? 1);
if (!$Viewer->permitted('site_search_many')) {
    $page = min($page, (int)(MAX_LOG_DEPTH / LOG_ENTRIES_PER_PAGE));
}

$search    = $_GET['search'] ?? '';
$paginator = new Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, $page);
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
