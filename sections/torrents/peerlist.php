<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    error(404);
}

$paginator = new Gazelle\Util\Paginator(PEERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($torrent->peerlistTotal());

echo $Twig->render('torrent/peerlist.twig', [
    'is_admin'   => check_perms('users_mod'),
    'linkbox'    => $paginator->linkboxJS('show_peers', $torrent->id()),
    'list'       => $torrent->peerlistPage($LoggedUser['ID'], $paginator->limit(), $paginator->offset()),
    'torrent_id' => $torrent->id(),
    'user_id'    => $LoggedUser['ID'],
]);
