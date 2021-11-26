<?php

if (!$Viewer->permitted('site_analysis')) {
    error(403);
}

$errMan    = new Gazelle\Manager\ErrorLog;
$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($errMan->total());

echo $Twig->render('debug/error-analysis.twig', [
    'list'      => $errMan->list($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
]);
