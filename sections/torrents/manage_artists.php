<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

authorize();

$roleAliasList = [];
foreach (explode(',', $_POST['artists'] ?? '') as $roleAliasId) {
    [$role, $aliasId] = array_map('intval', explode(';', $roleAliasId));
    if ($role && $aliasId) {
        $roleAliasList[] = [$role, $aliasId];
    }
}
if (!$roleAliasList) {
    Error400::error('No artists to manage');
}

$tgroup = new Manager\TGroup()->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    Error404::error();
}

if (($_POST['manager_action'] ?? '') == 'delete') {
    $tgroup->artistRole()->removeList($roleAliasList, $Viewer);
} else {
    $newRole = (int)($_POST['importance'] ?? 0);
    if ($newRole === 0 || !isset(ARTIST_TYPE[$newRole])) {
        Error400::error('Unknown new artist role');
    }
    $tgroup->artistRole()->modifyList($roleAliasList, $newRole, $Viewer);
}
$tgroup->refresh();

header("Location: {$tgroup->location()}");
