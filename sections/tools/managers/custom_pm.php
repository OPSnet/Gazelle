<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

use sad_spirit\pg_wrapper\exceptions\server\ProgrammingException;

if (!$Viewer->permitted('admin_site_debug')) {
    Error403::error();
}

$delivered = false;
$error     = false;
$idList    = [];

if (isset($_POST['query'])) {
    authorize();
    $result = [];
    switch ($_POST['source']) {
        case 'my':
            $db = DB::DB(readWrite: false);
            try {
                $db->prepared_query($_POST['query']);
                $result = $db->collect(0);
            } catch (\mysqli_sql_exception  $e) {
                $error = $e->getMessage();
            } catch (\Exception  $e) {
                $error = "General error: {$e->getMessage()}";
            }
            break;
        case 'pg':
            try {
                $result = new DB\Pg(PG_RO_DSN)->columnex($_POST['query']);
            } catch (ProgrammingException $e) {
                $error = $e::class . " " . $e->getMessage();
            }
            break;
        default:
            Error400::error("Bad database source, try again");
    }
    if (!$error && !$result && $_POST['query']) {
        $error = "Query returned 0 rows";
    }

    // we don't actually know if the query returned user ids, so check them
    $userMan = new Manager\User();
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
    'message'   => new Util\Textarea('message', $delivered ? '' : $_POST['message'] ?? '', 100, 6),
    'query'     => $delivered ? '' : $_POST['query'] ?? '',
    'source'    => $_POST['source'] ?? 'my',
    'viewer'    => $Viewer,
]);
