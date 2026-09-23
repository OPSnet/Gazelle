<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_database_specifics')) {
    Error403::error();
}

// View table definition
if (preg_match('/([\w-]+(?:\.[\w-]+)?)/', $_GET['table'] ?? '', $match)) {
    $table = new DB\PgTable($match[1]);
    if (!$table->exists()) {
        Error404::error("No such Postgresql table {$match[1]}");
    }
    echo $Twig->render('admin/pg-table.twig', [
        'table' => $table,
    ]);
    return;
}

$info = new DB\PgInfo(
    Enum\PgInfoOrderBy::tryFrom($_GET['order'] ?? '') ?? Enum\PgInfoOrderBy::tableName,
    Enum\Direction::tryFrom($_GET['sort'] ?? '') ?? Enum\Direction::ascending,
);

echo $Twig->render('admin/pg-table-summary.twig', [
    'header' => new Util\SortableTableHeader(
        Enum\PgInfoOrderBy::tableName->value,
        DB\PgInfo::columnList()
    ),
    'list' => $info->info(),
]);
