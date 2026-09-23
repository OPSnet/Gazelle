<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!($Viewer->permitted('site_delete_artist') && $Viewer->permitted('torrents_delete'))) {
    Error403::error();
}
authorize();

$artist = new Manager\Artist()->findById((int)($_GET['artistid'] ?? 0));
if (is_null($artist)) {
    Error404::error();
}

$tgMan = new Manager\TGroup();
$tgroupList = array_map($tgMan->findById(...), $artist->tgroupIdUsage());

$reqMan = new Manager\Request();
$requestList = array_map($reqMan->findById(...), $artist->requestIdUsage());

if (count($tgroupList) + count($requestList) > 0) {
    echo $Twig->render('artist/remove-fail.twig', [
        'artist'       => $artist,
        'request_list' => $requestList,
        'tgroup_list'  => $tgroupList,
    ]);
    return;
}

$name = $artist->name();
$artist->remove();

echo $Twig->render('artist/remove-success.twig', [
    'name' => $name,
]);
