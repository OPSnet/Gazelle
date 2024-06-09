<?php

if (!$Viewer->permitted('site_database_specifics')) {
    error(403);
}

// View table definition
$db = Gazelle\DB::DB();
if (!empty($_GET['table']) && preg_match('/([\w-]+)/', $_GET['table'], $match)) {
    $tableName = $match[1];
    $siteInfo = new Gazelle\SiteInfo();
    if (!$siteInfo->tableExists($tableName)) {
        error("No such table");
    }
    echo $Twig->render('admin/mysql-table.twig', [
        'definition' => $db->row('SHOW CREATE TABLE ' . $tableName)[1],
        'table_name' => $tableName,
        'table_read' => $siteInfo->tableRowsRead($tableName),
        'index_read' => $siteInfo->indexRowsRead($tableName),
        'stats'      => $siteInfo->tableStats($tableName),
    ]);
    exit;
}

$header = new Gazelle\Util\SortableTableHeader('name', [
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
switch ($mode) {
    case 'merge':
        $tableColumn = "replace(table_name, 'deleted_', '')";
        $where = '';
        break;
    case 'exclude':
        $tableColumn = 'table_name';
        $where = "AND table_name NOT LIKE 'deleted%'";
        break;
    default:
        $tableColumn = 'table_name';
        $where = '';
        break;
}

$db->prepared_query("
    SELECT $tableColumn AS table_name,
        engine AS engine,
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
$list = $db->to_array('table_name', MYSQLI_ASSOC, false);

$data = [];
foreach ($list as $name => $info) {
    if ($header->getSortKey() === 'freeratio') {
        $data[$name] = round($info['data_length'] == 0 ? 0 : $info['data_free'] / $info['data_length'], 2);
    } elseif ($header->getSortKey() === 'name') {
        $data[$name] = $info['total_length'];
    } else {
        $data[$name] = $info[$orderBy];
    }
}

echo $Twig->render('admin/mysql-table-summary.twig', [
    'graph' => [
        'data'  => $data,
        'title' => $header->current()['alt'],
    ],
    'header' => $header,
    'list'   => $list,
]);
