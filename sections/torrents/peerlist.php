<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    error(404);
}

$paginator = new Gazelle\Util\Paginator(PEERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($torrent->peerlistTotal());

echo $Twig->render('torrent/peerlist.twig', [
    'is_admin'   => $Viewer->permitted('users_mod'),
    'list'       => $torrent->peerlistPage($Viewer->id(), $paginator->limit(), $paginator->offset()),
    'paginator'  => $paginator,
    'torrent_id' => $torrent->id(),
    'url_stem'   => (new Gazelle\Stylesheet($Viewer))->imagePath(),
    'user_id'    => $Viewer->id(),
]);
