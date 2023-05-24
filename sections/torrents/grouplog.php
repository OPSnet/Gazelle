<?php

$tgroupId = (int)($_GET['id'] ?? 0);
if (!$tgroupId) {
    // we may not have a torrent group because it has already been merged elsewhere
    // so the best we can hope for is something that looks like an integer
    error(404);
}
$tgroup = (new Gazelle\Manager\TGroup)->findById($tgroupId);

echo $Twig->render('tgroup/group-log.twig', [
    'group_id' => $tgroupId,
    'title'    => is_null($tgroup) ? "Group $tgroupId" : $tgroup->link(),
    'log'      => (new Gazelle\Manager\SiteLog(new Gazelle\Manager\User))->tgroupLogList($tgroupId),
]);
