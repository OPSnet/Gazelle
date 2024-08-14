<?php
/** @phpstan-var \Twig\Environment $Twig */

$siteLog   = new Gazelle\Manager\SiteLog(new Gazelle\Manager\User());
$paginator = new Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$search    = $_GET['search'] ?? '';

$page = $siteLog->page(LOG_ENTRIES_PER_PAGE, $paginator->offset(), $search);
$paginator->setTotal($siteLog->totalMatches());

echo $Twig->render('sitelog.twig', [
    'search'    => $search,
    'paginator' => $paginator,
    'page'      => $page,
]);
