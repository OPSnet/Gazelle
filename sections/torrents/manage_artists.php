<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
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
    error(0);
}

$tgroup = (new Gazelle\Manager\TGroup())->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}

if (($_POST['manager_action'] ?? '') == 'delete') {
    $tgroup->artistRole()->removeList($roleAliasList, $Viewer, new Gazelle\Log());
} else {
    $newRole = (int)($_POST['importance'] ?? 0);
    if ($newRole === 0 || !isset(ARTIST_TYPE[$newRole])) {
        error(0);
    }
    $tgroup->artistRole()->modifyList($roleAliasList, $newRole, $Viewer, new Gazelle\Log());
}
$tgroup->refresh();

header("Location: {$tgroup->location()}");
