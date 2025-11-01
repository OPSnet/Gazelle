<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$torrent = new Manager\Torrent()->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    Error404::error();
}

$paginator = new Util\Paginator(PEERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($torrent->seederTotal());

header('Content-type: application/json');
echo $Twig->render('torrent/seederlist.twig', [
    'list'      => $torrent->seederList($Viewer, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'torrent'   => $torrent,
    'url_stem'  => new User\Stylesheet($Viewer)->imagePath(),
    'viewer'    => $Viewer,
]);
