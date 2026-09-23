<?php

/**
 * @phpstan-var \Twig\Environment $Twig
 * @phpstan-var \Gazelle\User $Viewer
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_audit_edit')) {
    Error403::error();
}
authorize();

$userMan = new Manager\User();
$user = $userMan->findById((int)($_REQUEST['id'] ?? 0));
if (is_null($user)) {
    Error404::error();
}

$idList = $_REQUEST['id_list'] ?? '';
if (!$idList) {
    header("Location: ?action=audit&edit=1&id={$user->id}");
    return;
}
if ($Viewer->hashHmac('audit', $idList) !== $_REQUEST['sig']) {
    Error400::error('bad signature');
}
$user->auditTrail()->modifyEventList(
    array_map(intval(...), explode(',', $idList)),
    trim($_REQUEST['note']),
    $Viewer
);

header("Location: {$user->location()}&action=audit");
