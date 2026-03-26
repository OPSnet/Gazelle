<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();
if (!$Viewer->permitted('site_edit_wiki')) {
    json_die('failure', 'forbidden');
}
$tgroup = new Manager\TGroup()->findById((int)$_GET['groupid']);
$coverId = (int)$_GET['id'];
if (!$coverId || is_null($tgroup)) {
    json_die('failure', 'bad parameters');
}

if ($tgroup->artwork()->removeArtwork($coverId, $Viewer)) {
    json_print("success", ['id' => $coverId]);
} else {
    json_die('failure', 'bad coverId');
}
