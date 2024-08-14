<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

/***************************************************************
* This page handles the backend of the "new group" function
* which splits a torrent off into a new group.
****************************************************************/

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$artistName = trim($_POST['artist']);
$title      = trim($_POST['title']);
$year       = (int)$_POST['year'];
if (!$year || empty($title) || empty($artistName)) {
    error(0);
}

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('Torrent does not exist!');
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

$new = (new Gazelle\Manager\TGroup())->createFromTorrent(
    $torrent,
    $artistName,
    $title,
    $year,
    new Gazelle\Manager\Artist(),
    new Gazelle\Manager\Bookmark(),
    new Gazelle\Manager\Comment(),
    new Gazelle\Manager\Vote(),
    new Gazelle\Log(),
    $Viewer,
);

header('Location: ' . $new->location());
