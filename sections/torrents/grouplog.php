<?php

$groupId = (int)$_GET['id'];
if (!$groupId) {
    error(404);
}
$tgMan = new Gazelle\Manager\TGroup;
$tgroup = $tgMan->findById($groupId);

echo $Twig->render('tgroup/group-log.twig', [
    'group_id' => $groupId,
    'title'    => is_null($tgroup) ? "Group $groupId" : $tgroup->link(),
    'log'      => $tgMan->groupLog($groupId),
]);
