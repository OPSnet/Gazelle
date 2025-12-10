<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

header('Content-Type: application/json');

$lastfm   = new Util\LastFM();
$username = $_GET['username'] ?? null;
$mode     = $_REQUEST['mode'] ?? '';
if (!$username && $mode != 'weekly') {
    echo json_encode(null);
    exit;
}
echo json_encode(match ($mode) {
    'flush'       => $username == $lastfm->username($Viewer) && $lastfm->flush($username),
    'last_track'  => $lastfm->lastTrack($username),
    'top_artists' => $lastfm->topArtists($username),
    'top_albums'  => $lastfm->topAlbums($username),
    'top_tracks'  => $lastfm->topTracks($username),
    'weekly'      => $lastfm->weeklyArtists(),
    default       => null,
});
