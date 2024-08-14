<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use Gazelle\Enum\Direction;
use Gazelle\Enum\MysqlInfoOrderBy;
use Gazelle\Enum\MysqlTableMode;

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

$info = (new Gazelle\DB\MysqlInfo(
    Gazelle\DB\MysqlInfo::lookupTableMode($_GET['mode'] ?? MysqlTableMode::all->value),
    Gazelle\DB\MysqlInfo::lookupOrderby($_GET['order'] ?? MysqlInfoOrderBy::tableName->value),
    Gazelle\DB::lookupDirection($_GET['sort'] ?? Direction::ascending->value))
);
$list = $info->info();
$column = $info->orderBy() == MysqlInfoOrderBy::tableName
    ? MysqlInfoOrderBy::tableRows->value
    : $info->orderBy()->value;
$data = [];
foreach ($list as $t) {
    $data[$t['table_name']] = $t[$column];
}

echo $Twig->render('admin/mysql-table-summary.twig', [
    'header' => new \Gazelle\Util\SortableTableHeader(
        MysqlInfoOrderBy::tableName->value,
        Gazelle\DB\MysqlInfo::columnList(),
    ),
    'list'  => $list,
    'graph' => [
        'data'  => $data,
        'title' => $info->headerAlt(),
    ],
]);
