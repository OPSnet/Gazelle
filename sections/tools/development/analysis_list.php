<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;
ini_set('memory_limit', '256M');

if (!$Viewer->permitted('site_analysis')) {
    Error403::error();
}

$errorLog = new Manager\ErrorLog();
$remove = array_key_extract_suffix('clear-', $_POST);
if ($remove) {
    $removed = $errorLog->removeList($remove);
} elseif (isset($_POST['slow-clear'])) {
    $removed = $errorLog->removeSlow((float)($_POST['slow'] ?? 60.0));
} else {
    $removed = -1;
}

if (isset($_REQUEST['filter']) && isset($_REQUEST['search'])) {
    $errorLog->setFilter(trim($_REQUEST['search']));
}

$paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($errorLog->total());

$heading = new Util\SortableTableHeader('updated', [
    // see Gazelle\ErrorLog for these table aliases
    'id'       => ['dbColumn' => 'id',           'defaultSort' => 'desc', 'text' => 'Case'],
    'duration' => ['dbColumn' => 'duration',     'defaultSort' => 'desc', 'text' => 'Duration'],
    'memory'   => ['dbColumn' => 'memory',       'defaultSort' => 'desc', 'text' => 'Memory'],
    'error'    => ['dbColumn' => 'jsonb_array_length(error_list)',
        'defaultSort' => 'desc', 'text' => 'Errors'],
    'nr_query' => ['dbColumn' => 'nr_query',     'defaultSort' => 'desc', 'text' => 'Queries'],
    'nr_cache' => ['dbColumn' => 'nr_cache',     'defaultSort' => 'desc', 'text' => 'Cache'],
    'seen'     => ['dbColumn' => 'seen',         'defaultSort' => 'desc', 'text' => 'Seen'],
    'created'  => ['dbColumn' => 'created',      'defaultSort' => 'asc',  'text' => 'First'],
    'updated'  => ['dbColumn' => 'updated',      'defaultSort' => 'desc', 'text' => 'Latest'],
    'uri'      => ['dbColumn' => 'uri',          'defaultSort' => 'asc',  'text' => 'URI'],
]);

echo $Twig->render('debug/analysis-list.twig', [
    'heading'   => $heading,
    'list'      => $errorLog->list(
        $heading->orderBy(),
        $heading->dir(),
        $paginator->limit(),
        $paginator->offset()
    ),
    'paginator' => $paginator,
    'removed'   => $removed,
    'search'    => $_REQUEST['search'] ?? '',
    'viewer'    => $Viewer,
]);
