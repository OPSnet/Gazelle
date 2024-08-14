<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_debug')) {
    error(403);
}

$delivered = false;
$error     = false;
$idList    = [];

if (isset($_POST['query'])) {
    authorize();
    if (!preg_match('/\s*select /i', $_POST['query'])) {
        error("Only SELECT queries are permitted");
    }
    $result = [];
    switch ($_POST['source']) {
        case 'my':
            $db = Gazelle\DB::DB();
            try {
                $db->prepared_query($_POST['query']);
                $result = $db->collect(0, false);
            } catch (\mysqli_sql_exception  $e) {
                $error = $e->getMessage();
            } catch (\Exception  $e) {
                $error = "General error: {$e->getMessage()}";
            }
            break;
        case 'pg':
            try {
                $result = (new \Gazelle\DB\Pg(GZPG_DSN))->column($_POST['query']);
            } catch (\Exception $e) {
                $error = $e::class . " " . $e->getMessage();
            }
            break;
        default:
            error("Bad database source, try again");
    }
    if (!$error && !$result && $_POST['query']) {
        $error = "Query returned 0 rows";
    }

    // we don't actually know if the query returned user ids, so check them
    $userMan = new Gazelle\Manager\User();
    foreach ($result as $userId) {
        $user = $userMan->findById($userId);
        if ($user) {
            $idList[] = $userId;
        }
    }

    if (isset($_POST['send'])) {
        if (empty($_POST['subject'])) {
            $error = "You must supply a subject for the message";
        } else {
            $delivered = $userMan->sendCustomPM(
                isset($_POST['anonymous']) ? null : $Viewer,
                trim($_POST['subject']),
                trim($_POST['message']),
                $idList
            );
        }
    }
}


echo $Twig->render('admin/custom-pm.twig', [
    'error'     => $error,
    'delivered' => $delivered,
    'id_list'   => $delivered ? [] : $idList,
    'message'   => new Gazelle\Util\Textarea('message', $delivered ? '' : $_POST['message'] ?? '', 100, 6),
    'query'     => $delivered ? '' : $_POST['query'] ?? '',
    'source'    => $_POST['source'] ?? 'my',
    'viewer'    => $Viewer,
]);
