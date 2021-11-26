<?php

if (!$Viewer->permitted('site_analysis')) {
    error(403);
}

$remove = array_map(
    fn ($key) => (int)(explode('-', $key)[1]),
    array_keys(array_filter($_POST, function ($x) { return preg_match('/^clear-\d+$/', $x);}, ARRAY_FILTER_USE_KEY))
);

$errMan = new Gazelle\Manager\ErrorLog;
if ($remove) {
    $errMan->remove($remove);
}

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($errMan->total());

echo $Twig->render('debug/error-analysis.twig', [
    'auth'      => $Viewer->auth(),
    'list'      => $errMan->list($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
]);
