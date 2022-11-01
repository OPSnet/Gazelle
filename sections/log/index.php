<?php

$search = $_GET['search'] ?? '';

$siteLog = new Gazelle\Manager\SiteLog($Debug, new Gazelle\Manager\User);
$paginator = new Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$siteLog->load($paginator->page(), $paginator->offset(), $search);
$paginator->setTotal($siteLog->totalMatches());

echo $Twig->render('sitelog.twig', [
    'search'    => $search,
    'paginator' => $paginator,
    'sitelog'   => $siteLog,
]);
