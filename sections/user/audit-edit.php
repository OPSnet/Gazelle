<?php
/** @phpstan-var \Twig\Environment $Twig */
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_audit_view')) {
    Error403::error();
}
$userMan = new Manager\User();
$user = $userMan->findById((int)($_REQUEST['id'] ?? 0));
if (is_null($user)) {
    Error404::error();
}

$idList = array_map('intval', $_REQUEST['idlist'] ?? []);
if (!$idList) {
    header("Location: ?action=audit&edit=1&id={$user->id}");
    exit;
}

$eventList = $user->auditTrail()->eventList($idList);
echo $Twig->render('user/audit-edit.twig', [
    'id_list' => implode(
        ',',
        array_map(fn ($e) => $e['id_audit_trail_user'], $eventList),
    ),
    'note' => new Util\Textarea(
        'note',
        implode(
            "\n\n",
            array_map(fn ($e) => $e['note'], $eventList)
        ),
        90,
        10,
    ),
    'user'   => $user,
    'viewer' => $Viewer,
]);
