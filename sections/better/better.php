<?php

$userMan = new Gazelle\Manager\User;
if (isset($_GET['userid']) && $Viewer->permitted('users_override_paranoia')) {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
} else {
    $user = $Viewer;
}

if ($_GET['method'] === 'single') {
    $filter = 'all';
    $type   = 'single';
} else {
    $filter = $_GET['filter'] ?? 'all';
    $type   = $_GET['type'] ?? 'artwork';
}

$better = match($type) {
    'artistcollage' => new Gazelle\Better\ArtistCollage($user, $filter, new Gazelle\Manager\Artist),
    'artistdesc'    => new Gazelle\Better\ArtistDescription($user, $filter, new Gazelle\Manager\Artist),
    'artistdiscogs' => new Gazelle\Better\ArtistDiscogs($user, $filter, new Gazelle\Manager\Artist),
    'artistimg'     => new Gazelle\Better\ArtistImage($user, $filter, new Gazelle\Manager\Artist),
    'artwork'       => new Gazelle\Better\Artwork($user, $filter, (new Gazelle\Manager\TGroup)->setViewer($Viewer)),
    'checksum'      => new Gazelle\Better\Checksum($user, $filter, (new Gazelle\Manager\Torrent)->setViewer($Viewer)),
    'single'        => new Gazelle\Better\SingleSeeded($user, $filter, (new Gazelle\Manager\Torrent)->setViewer($Viewer)),
    'files', 'folders', 'lineage', 'tags'
                    => (new Gazelle\Better\Bad($user, $filter, new Gazelle\Manager\Torrent))->setBadType($type),
    default         => error(0),
};

if (isset($_GET['remove']) && $better instanceof Gazelle\Better\Bad && $Viewer->permitted('admin_reports')) {
    $torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['remove']);
    if ($torrent) {
        $torrent->removeFlag($better->torrentFlag());
    }
}

$uploader = null;
if ($type == 'single') {
    $uploader = $userMan->find($_GET['uploader'] ?? '');
    if ($uploader) {
        $better->setUploader($uploader);
    }
}

$search = $_GET['search'] ?? '';
if ($search) {
    $better->setSearch($search);
}

$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($better->total());

echo $Twig->render('better/better.twig', [
    'better'    => $better,
    'filter'    => $filter,
    'search'    => $search,
    'snatcher'  => new Gazelle\User\Snatch($Viewer),
    'type'      => $type,
    'paginator' => $paginator,
    'uploader'  => $uploader,
    'viewer'    => $Viewer,
]);
