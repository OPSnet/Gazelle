<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$torMan = new Manager\Torrent();
$torrent = $torMan->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    $torrent = $torMan->findDeletedById((int)$_GET['torrentid']);
}
if (is_null($torrent)) {
    Error404::error();
}

echo $Twig->render('riplog/list.twig', [
    'collection' => new Manager\Riplog()->collection($torrent),
    'torrent'    => $torrent,
    'viewer'     => $Viewer,
]);
