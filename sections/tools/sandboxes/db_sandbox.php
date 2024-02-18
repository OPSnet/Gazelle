<?php

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
}

$execute = false;
if (isset($_GET['debug'])) {
    $query = html_entity_decode(base64_decode($_GET['debug']));
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
} elseif (isset($_GET['table'])) {
    $query = (new Gazelle\DB())->selectQuery($_GET['table']);
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
} elseif (!empty($_POST['query'])) {
    $query = trim($_POST['query']);
    if (preg_match('@^(?:show(\s+[\w%\';]+)+|(?:explain\s+)?select\b(?:[\s\w()<>/.,!`\'"=*+-])+\bfrom)@i', $query) !== 1) {
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
        $db = Gazelle\DB::DB();
        $db->prepared_query($query);
        $result = $db->to_array(false, MYSQLI_ASSOC, false);
    } catch (\Exception | \Error $e) {
        $error = $e->getMessage();
    }
}

echo $Twig->render('debug/db-sandbox.twig', [
    'query'  => $query,
    'rows'   => $textAreaRows,
    'result' => $result,
    'error'  => $error,
]);
