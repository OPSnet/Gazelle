<?php

if (empty($_GET['order_by']) || !isset(Gazelle\Search\Torrent::$SortOrders[$_GET['order_by']])) {
    $OrderBy = 'time';
} else {
    $OrderBy = $_GET['order_by'];
}
$OrderWay = ($_GET['order_way'] ?? 'desc');
$GroupResults = ($_GET['group_results'] ?? '1') != '0';
$Page = (int)($_GET['page'] ?? 1);

$Search = new Gazelle\Search\Torrent(
    $GroupResults,
    $OrderBy,
    $OrderWay,
    $Page,
    TORRENTS_PER_PAGE,
    $Viewer->permitted('site_search_many')
);
$Results     = $Search->query($_GET);
$resultTotal = $Search->record_count();
if (!$Viewer->permitted('site_search_many')) {
    $resultTotal = min($resultTotal, SPHINX_MAX_MATCHES);
}

if ($Results === false) {
    json_die('failure', 'Search returned an error. Make sure all parameters are valid and of the expected types.');
}
if ($resultTotal == 0) {
    json_die('success', [
        'results' => [],
        'youMightLike' => [] // This slow and broken feature has been removed
    ]);
}


(new Gazelle\Json\TGroupList(
    new Gazelle\User\Bookmark($Viewer),
    new Gazelle\User\Snatch($Viewer),
    new Gazelle\Manager\Artist,
    (new Gazelle\Manager\TGroup)->setViewer($Viewer),
    (new Gazelle\Manager\Torrent)->setViewer($Viewer),
    $Results,
    $GroupResults,
    $resultTotal,
    $Page
))
    ->setVersion(2)
    ->emit();
