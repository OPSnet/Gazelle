<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_view_torrent_snatchlist')) {
    error(403);
}
$torrent = (new Gazelle\Manager\Torrent())->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    error(404);
}

$paginator = new Gazelle\Util\Paginator(PEERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($torrent->snatchTotal());

echo $Twig->render('torrent/snatchlist.twig', [
    'list'       => $torrent->snatchList($Viewer, $paginator->limit(), $paginator->offset()),
    'paginator'  => $paginator,
    'torrent_id' => $torrent->id(),
    'url_stem'   => (new Gazelle\User\Stylesheet($Viewer))->imagePath(),
    'viewer_id'  => $Viewer->id(),
]);
