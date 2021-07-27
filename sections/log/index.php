<?php

enforce_login();
$search = $_GET['search'] ?? '';

$siteLog = new Gazelle\Manager\SiteLog($Debug);
$paginator = new Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$siteLog->load($paginator->page(), $paginator->offset(), $search);
$paginator->setTotal($siteLog->totalMatches());

View::show_header("Site log");
echo $Twig->render('sitelog.twig', [
    'search'    => $search,
    'paginator' => $paginator,
    'sitelog'   => $siteLog,
]);
View::show_footer();
