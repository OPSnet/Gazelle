<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!($Viewer->permitted('site_delete_artist') && $Viewer->permitted('torrents_delete'))) {
    error(403);
}
authorize();

$artist = (new Gazelle\Manager\Artist())->findById((int)($_GET['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}

$tgMan = new Gazelle\Manager\TGroup();
$tgroupList = array_map(fn ($id) => $tgMan->findById($id), $artist->tgroupIdUsage());

$reqMan = new Gazelle\Manager\Request();
$requestList = array_map(fn ($id) => $reqMan->findById($id), $artist->requestIdUsage());

if (count($tgroupList) + count($requestList) > 0) {
    echo $Twig->render('artist/remove-fail.twig', [
        'artist'       => $artist,
        'request_list' => $requestList,
        'tgroup_list'  => $tgroupList,
    ]);
    exit;
}

$name = $artist->name();
$artist->remove($Viewer, new Gazelle\Log());

echo $Twig->render('artist/remove-success.twig', [
    'name' => $name,
]);
