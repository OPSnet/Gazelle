<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_recovery')) {
    Error403::error();
}

$recovery = new Manager\Recovery();
if (isset($_GET['task'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        switch ($_GET['task']) {
            case 'accept':
                $ok = $recovery->accept($id, $Viewer->id, $Viewer->username());
                $message = $ok ? '<font color="#008000">Invite sent</font>' : '<font color="#800000">Invite not sent, check log</font>';
                break;
            case 'deny':
                $recovery->deny($id, $Viewer->username());
                $message = sprintf('<font color="orange">Request %d was denied</font>', $id);
                break;
            case 'unclaim':
                $recovery->unclaim($id, $Viewer->username());
                $message = sprintf('<font color="orange">Request %d was unclaimed</font>', $id);
                break;
            default:
                Error403::error();
        }
    }
} else {
    foreach (explode(' ', 'token username announce email') as $field) {
        if (array_key_exists($field, $_POST)) {
            $value = trim($_POST[$field]);
            if (strlen($value)) {
                header("Location: /recovery.php?action=search&$field=$value");
                exit;
            }
        }
    }
}

$state = $_GET['state'] ?? 'pending';
$paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($recovery->total($state, $Viewer->id));

echo $Twig->render('recovery/admin.twig', [
    'list'        => $recovery->page($paginator->limit(), $paginator->offset(), $state, $Viewer->id),
    'message'     => $message ?? null,
    'paginator'   => $paginator,
    'state'       => $state,
    'viewer'      => $Viewer,
]);
