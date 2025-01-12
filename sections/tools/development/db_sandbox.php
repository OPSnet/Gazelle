<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use Gazelle\Enum\SourceDB;
use Gazelle\Util\Text;

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
}

$src = ($_REQUEST['src'] ?? SourceDB::mysql->value) == SourceDB::mysql->value
    ? SourceDB::mysql
    : SourceDB::postgres;

$execute = false;

if (isset($_GET['debug'])) {
    $data = json_decode(Text::base64UrlDecode($_GET['debug']), true);
    $query = trim($data['query']);
    if ($src === SourceDB::postgres && !empty($data['args'])) {
        $query .= "\n-- " . implode(', ', $data['args']);
    }
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
} elseif (isset($_GET['table'])) {
    $query = (new Gazelle\DB())->selectQuery($_GET['table']);
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
} elseif (!empty($_POST['query'])) {
    $query = trim($_POST['query']);
    if (preg_match('@^(?:show(\s+[\w%\';]+)+|(?:explain\s+)?select\b(?:[\s\w()<>#&/:.,?!`\'"=*+-])+\bfrom)@i', $query) !== 1) {
        error('Invalid query');
    }
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
    $execute = true;
} else {
    $query = null;
    $textAreaRows = 8;
}

$error  = false;
$result = [];
if ($execute) {
    try {
        if ($src == SourceDB::postgres) {
            $db = new \Gazelle\DB\Pg(GZPG_DSN);
            $result = $db->all($query);
        } else {
            $db = Gazelle\DB::DB();
            $db->prepared_query($query);
            $result = $db->to_array(false, MYSQLI_ASSOC, false);
        }
    } catch (\Exception | \Error $e) {
        $error = $e->getMessage();
    }
}

echo $Twig->render('debug/db-sandbox.twig', [
    'query'  => $query,
    'rows'   => $textAreaRows,
    'result' => $result,
    'source' => $src->value,
    'error'  => $error,
]);
