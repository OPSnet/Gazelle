<?php

use Gazelle\Enum\PgInfoOrderBy;
use Gazelle\Enum\Direction;

if (!$Viewer->permitted('site_database_specifics')) {
    error(403);
}

$info = new Gazelle\DB\PgInfo(
    Gazelle\DB\PgInfo::lookupOrderby($_GET['order'] ?? PgInfoOrderBy::tableName->value),
    Gazelle\DB::lookupDirection($_GET['sort'] ?? Direction::ascending->value)
);

echo $Twig->render('admin/pg-table-summary.twig', [
    'header' => new \Gazelle\Util\SortableTableHeader(
        PgInfoOrderBy::tableName->value,
        Gazelle\DB\PgInfo::columnList()
    ),
    'list' => $info->info(),
]);
