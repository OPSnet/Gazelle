<?php

header('Content-Type: application/json; charset=utf-8');

$lastfm   = new Gazelle\Util\LastFM;
$username = $_GET['username'] ?? null;
$mode     = $_REQUEST['mode'] ?? '';
if (!$username && $mode != 'weekly') {
    echo json_encode(null);
    exit;
}
echo json_encode(match ($mode) {
    'flush'       => $username == $lastfm->username($Viewer) ? $lastfm->flush($username) : false,
    'last_track'  => $lastfm->lastTrack($username),
    'top_artists' => $lastfm->topArtists($username),
    'top_albums'  => $lastfm->topAlbums($username),
    'top_tracks'  => $lastfm->topTracks($username),
    'weekly'      => $lastfm->weeklyArtists(),
    default       => null,
});
