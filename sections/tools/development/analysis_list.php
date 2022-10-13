<?php

if (!$Viewer->permitted('site_analysis')) {
    error(403);
}

$remove = array_map(
    fn ($key) => (int)(explode('-', $key)[1]),
    array_keys(array_filter($_POST, function ($x) { return preg_match('/^clear-\d+$/', $x);}, ARRAY_FILTER_USE_KEY))
);

$errMan = new Gazelle\Manager\ErrorLog;
$removed = -1;
if ($remove) {
    $removed = $errMan->remove($remove);
} elseif (isset($_POST['slow-clear'])) {
    $removed = $errMan->removeSlow((float)($_POST['slow'] ?? 60.0));
}

if (isset($_REQUEST['filter']) && isset($_REQUEST['search'])) {
    $errMan->setFilter(trim($_REQUEST['search']));
}

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($errMan->total());

$heading = new Gazelle\Util\SortableTableHeader('updated', [
    // see Gazelle\ErrorLog for these table aliases
    'id'       => ['dbColumn' => 'error_log_id', 'defaultSort' => 'desc', 'text' => 'Case'],
    'duration' => ['dbColumn' => 'duration',     'defaultSort' => 'desc', 'text' => 'Duration'],
    'memory'   => ['dbColumn' => 'memory',       'defaultSort' => 'desc', 'text' => 'Memory'],
    'error'    => ['dbColumn' => 'json_length(cast(error_list as json))',
        'defaultSort' => 'desc', 'text' => 'Errors'],
    'nr_query' => ['dbColumn' => 'nr_query',     'defaultSort' => 'desc', 'text' => 'Queries'],
    'nr_cache' => ['dbColumn' => 'nr_cache',     'defaultSort' => 'desc', 'text' => 'Cache'],
    'seen'     => ['dbColumn' => 'seen',         'defaultSort' => 'desc', 'text' => 'Seen'],
    'created'  => ['dbColumn' => 'created',      'defaultSort' => 'asc',  'text' => 'First'],
    'updated'  => ['dbColumn' => 'updated',      'defaultSort' => 'desc', 'text' => 'Latest'],
]);

echo $Twig->render('debug/analysis-list.twig', [
    'auth'      => $Viewer->auth(),
    'heading'   => $heading,
    'list'      => $errMan->list($heading->getOrderBy(), $heading->getOrderDir(), $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'removed'   => $removed,
    'search'    => $_REQUEST['search'] ?? '',
]);
