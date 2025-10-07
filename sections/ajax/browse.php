<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (empty($_GET['order_by']) || !isset(Search\Torrent::$SortOrders[$_GET['order_by']])) {
    $OrderBy = 'time';
} else {
    $OrderBy = $_GET['order_by'];
}
$OrderWay = ($_GET['order_way'] ?? 'desc');
$GroupResults = ($_GET['group_results'] ?? '1') != '0';
$Page = (int)($_GET['page'] ?? 1);

$Search = new Search\Torrent(
    $GroupResults,
    $OrderBy,
    $OrderWay,
    $Page,
    TORRENTS_PER_PAGE,
    $Viewer->permitted('site_search_many'),
);
$Results     = $Search->query($_GET);
$resultTotal = $Search->record_count();
if (!$Viewer->permitted('site_search_many')) {
    $resultTotal = min($resultTotal, SPHINX_MAX_MATCHES);
}

if (!is_array($Results)) {
    json_die('failure', 'Search returned an error. Make sure all parameters are valid and of the expected types.');
}
if ($resultTotal == 0) {
    json_die('success', [
        'results' => [],
        'youMightLike' => [] // This slow and broken feature has been removed
    ]);
}


echo new Json\TGroupList(
    new User\Bookmark($Viewer),
    $Viewer->snatch(),
    $Results,
    $GroupResults,
    $resultTotal,
    $Page
)
    ->setVersion(2)
    ->response();
