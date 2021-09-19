<?php

if (!$Viewer->permitted('site_database_specifics')) {
    error(403);
}

// View table definition
if (!empty($_GET['table']) && preg_match('/([\w-]+)/', $_GET['table'], $match)) {
    $tableName = $match[1];

    View::show_header('Database Specifics - ' . $tableName);
    $siteInfo = new Gazelle\SiteInfo;
    echo $Twig->render('admin/db-table.twig', [
        'definition' => $DB->row('SHOW CREATE TABLE ' . $tableName)[1],
        'table_name' => $tableName,
        'table_read' => $siteInfo->tableRowsRead($tableName),
        'index_read' => $siteInfo->indexRowsRead($tableName),
    ]);
    View::show_footer();
    exit;
}

$header = new \Gazelle\Util\SortableTableHeader('totalsize', [
    'datafree'  => ['dbColumn' => 'data_free',      'defaultSort' => 'desc', 'text' => 'Free Size',  'alt' => 'free space'],
    'datasize'  => ['dbColumn' => 'data_length',    'defaultSort' => 'desc', 'text' => 'Data Size',  'alt' => 'table size'],
    'freeratio' => ['dbColumn' => 'CASE WHEN data_length = 0 THEN 0 ELSE data_free / data_length END', 'defaultSort' => 'desc', 'text' => 'Bloat %', 'alt' => 'table bloat'],
    'indexsize' => ['dbColumn' => 'index_length',   'defaultSort' => 'desc', 'text' => 'Index Size', 'alt' => 'index size'],
    'name'      => ['dbColumn' => 'table_name',     'defaultSort' => 'asc',  'text' => 'Name',       'alt' => 'name'],
    'rows'      => ['dbColumn' => 'table_rows',     'defaultSort' => 'desc', 'text' => 'Rows',       'alt' => 'row counts'],
    'rowsize'   => ['dbColumn' => 'avg_row_length', 'defaultSort' => 'desc', 'text' => 'Row Size',   'alt' => 'mean row length'],
    'totalsize' => ['dbColumn' => 'total_length',   'defaultSort' => 'desc', 'text' => 'Total Size', 'alt' => 'total table size'],
]);
$orderBy = $header->getOrderBy();
$orderDir = $header->getOrderDir();

$mode = $_GET['mode'] ?? 'show';
switch($mode) {
    case 'show':
        $tableColumn = 'table_name';
        $where = '';
        break;
    case 'merge':
        $tableColumn = "replace(table_name, 'deleted_', '')";
        $where = '';
        break;
    case 'exclude':
        $tableColumn = 'table_name';
        $where = "AND table_name NOT LIKE 'deleted%'";
        break;
}

$DB->prepared_query("
    SELECT $tableColumn AS table_name,
        engine,
        sum(table_rows) AS table_rows,
        avg(avg_row_length) AS avg_row_length,
        sum(data_length) AS data_length,
        sum(index_length) AS index_length,
        sum(index_length + data_length) AS total_length,
        sum(data_free) AS data_free
    FROM information_schema.tables
    WHERE table_schema = ? $where
    GROUP BY $tableColumn, engine
    ORDER by $orderBy $orderDir
    ", SQLDB
);
$Tables = $DB->to_array('table_name', MYSQLI_ASSOC, false);

$data = [];
foreach ($Tables as $name => $info) {
    if ($header->getSortKey() === 'freeratio') {
        $data[$name] = round($info['data_length'] == 0 ? 0 : $info['data_free'] / $info['data_length'], 2);
    } else {
        $data[$name] = $info[$orderBy];
    }
}

echo $Twig->render('admin/db-table-summary.twig', [
    'graph' => [
        'data'  => $data,
        'title' => $header->current()['alt'],
    ],
    'header' => $header,
    'list'   => $Tables,
]);
