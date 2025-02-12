<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

/* Move a torrent from one group to another */

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('Torrent does not exist!');
}

$tgMan = new Gazelle\Manager\TGroup();
$new = $tgMan->findById((int)($_POST['groupid'] ?? 0));
if (is_null($new)) {
    error('The destination torrent group does not exist!');
}
if ($new->categoryName() !== 'Music') {
    error('Destination torrent group must be in the "Music" category.');
}

if ($torrent->groupId() === $new->id()) {
    header("Location: " . redirectUrl("torrents.php?action=edit&id=" . $torrent->groupId()));
    exit;
}

if (empty($_POST['confirm'])) {
    echo $Twig->render('torrent/confirm-move.twig', [
        'new'     => $new,
        'torrent' => $torrent,
        'viewer'  => $Viewer,
    ]);
    exit;
}

authorize();

$new->absorb($torrent, $Viewer);

header('Location: ' . $new->location());
