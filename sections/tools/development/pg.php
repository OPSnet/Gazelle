<?php

use Gazelle\Enum\PgInfoOrderBy;
use Gazelle\Enum\Direction;

if (!$Viewer->permitted('site_database_specifics')) {
    error(403);
}

$header = new Gazelle\Util\SortableTableHeader(PgInfoOrderBy::tableName->value, [
    PgInfoOrderBy::tableName->value    => ['dbColumn' => PgInfoOrderBy::tableName->value,    'defaultSort' => 'asc',  'text' => 'Table Name',     'alt' => 'table name'],
    PgInfoOrderBy::tableSize->value    => ['dbColumn' => PgInfoOrderBy::tableSize->value,    'defaultSort' => 'desc', 'text' => 'Table Size',     'alt' => 'table size'],
    PgInfoOrderBy::indexSize->value    => ['dbColumn' => PgInfoOrderBy::indexSize->value,    'defaultSort' => 'desc', 'text' => 'Index Size',     'alt' => 'index sizet'],
    PgInfoOrderBy::live->value         => ['dbColumn' => PgInfoOrderBy::live->value,         'defaultSort' => 'desc', 'text' => 'Live Rows',      'alt' => 'live rows'],
    PgInfoOrderBy::dead->value         => ['dbColumn' => PgInfoOrderBy::dead->value,         'defaultSort' => 'desc', 'text' => 'Dead Rows',      'alt' => 'dead rows'],
    PgInfoOrderBy::deadRatio->value    => ['dbColumn' => PgInfoOrderBy::deadRatio->value,    'defaultSort' => 'desc', 'text' => 'Dead Ratio %',   'alt' => 'dead ratio'],
    PgInfoOrderBy::analyzeDelta->value => ['dbColumn' => PgInfoOrderBy::analyzeDelta->value, 'defaultSort' => 'desc', 'text' => 'Last Analyze',   'alt' => 'last analyze'],
    PgInfoOrderBy::analyzeTotal->value => ['dbColumn' => PgInfoOrderBy::analyzeTotal->value, 'defaultSort' => 'desc', 'text' => 'Total Analyzes', 'alt' => 'total analyze'],
    PgInfoOrderBy::vacuumDelta->value  => ['dbColumn' => PgInfoOrderBy::vacuumDelta->value,  'defaultSort' => 'desc', 'text' => 'Last Vacuum',    'alt' => 'last vacuum'],
    PgInfoOrderBy::vacuumTotal->value  => ['dbColumn' => PgInfoOrderBy::vacuumTotal->value,  'defaultSort' => 'desc', 'text' => 'Total Vacuums',  'alt' => 'total vacuum'],
]);

echo $Twig->render('admin/pg-table-summary.twig', [
    'header' => $header,
    'list'   => (new Gazelle\DB\PgInfo(
        Gazelle\DB\PgInfo::lookupOrderby($header->getOrderBy()),
        Gazelle\DB::lookupDirection($header->getOrderDir()))
    )->info(),
]);
