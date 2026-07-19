<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Util\Text;

if (!$Viewer->permitted('admin_site_debug')) {
    Error403::error();
}

$src = ($_REQUEST['src'] ?? Enum\SourceDB::mysql->value) == Enum\SourceDB::mysql->value
    ? Enum\SourceDB::mysql
    : Enum\SourceDB::postgres;

$execute      = false;
$query        = null;
$table        = null;
$textAreaRows = 8;

if (isset($_GET['debug'])) {
    $data  = json_decode(Text::base64UrlDecode($_GET['debug']), true);
    $query = trim($data['query']);
    if ($src === Enum\SourceDB::postgres && !empty($data['args'])) {
        $query .= "\n-- " . implode(', ', $data['args']);
    }
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
} elseif (isset($_GET['table'])) {
    $table = $src === Enum\SourceDB::postgres
        ? new DB\PgTable($_GET['table'])
        : new DB\MysqlTable($_GET['table']);
    $textAreaRows = 8;
} elseif (!empty($_POST['query'])) {
    $query        = trim($_POST['query']);
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
    $execute      = true;
}

$error  = false;
$result = [];
if ($execute) {
    try {
        if ($src == Enum\SourceDB::postgres) {
            $db = new \Gazelle\DB\Pg(PG_RO_DSN);
            $result = $db->all($query);
        } else {
            $db = DB::DB(readWrite: false);
            $db->prepared_query($query);
            $result = $db->to_array(false, MYSQLI_ASSOC);
        }
    } catch (\Exception | \Error $e) {
        $error = $e->getMessage();
    }
}

echo $Twig->render('debug/db-sandbox.twig', [
    'error'  => $error,
    'plain'  => isset($_REQUEST['plain']),
    'query'  => $query,
    'rows'   => $textAreaRows,
    'result' => $result,
    'source' => $src->value,
    'table'  => $table,
]);
