<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

/***************************************************************
* This page handles the backend of the "new group" function
* which splits a torrent off into a new group.
****************************************************************/

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

$artistName = trim($_POST['artist']);
$title      = trim($_POST['title']);
$year       = (int)$_POST['year'];
if (!$year || empty($title) || empty($artistName)) {
    Error400::error('Missing parameters to set up new group');
}

$torrent = new Manager\Torrent()->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error400::error('Torrent does not exist!');
}

// double check
if (empty($_POST['confirm'])) {
    echo $Twig->render('torrent/confirm-split.twig', [
        'artist'  => $_POST['artist'],
        'title'   => $_POST['title'],
        'year'    => $_POST['year'],
        'torrent' => $torrent,
        'viewer'  => $Viewer,
    ]);
    exit;
}

$new = new Manager\TGroup()->createFromTorrent(
    $torrent,
    $artistName,
    $title,
    $year,
    new Manager\Artist(),
    new Manager\Bookmark(),
    new Manager\Comment(),
    new Manager\Vote(),
    $Viewer,
);

header('Location: ' . $new->location());
