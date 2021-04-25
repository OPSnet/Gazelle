<?php
$torrentId = (int)$_GET['torrentid'];
if (!$torrentId) {
    error(404);
}

$torMan = new Gazelle\Manager\Torrent;
$paginator = new Gazelle\Util\Paginator(PEERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($torMan->peerlistTotal($torrentId));

echo $Twig->render('torrent/peerlist.twig', [
    'is_admin'   => check_perms('users_mod'),
    'linkbox'    => $paginator->linkboxJS('show_peers', $torrentId),
    'list'       => $torMan->peerlistPage($torrentId, $LoggedUser['ID'], $paginator->limit(), $paginator->offset()),
    'torrent_id' => $torrentId,
    'user_id'    => $LoggedUser['ID'],
]);
