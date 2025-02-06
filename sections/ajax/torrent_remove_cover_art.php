<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();
if (!$Viewer->permitted('site_edit_wiki')) {
    json_die('failure', 'forbidden');
}
$tgroup = (new Gazelle\Manager\TGroup())->findById((int)$_GET['groupid']);
$coverId = (int)$_GET['id'];
if (!$coverId || is_null($tgroup)) {
    json_die('failure', 'bad parameters');
}

if ($tgroup->removeCoverArt($coverId, $Viewer)) {
    json_print("success", ['id' => $coverId]);
} else {
    json_die('failure', 'bad coverId');
}
