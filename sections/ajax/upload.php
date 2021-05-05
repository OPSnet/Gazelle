<?php
// echo out the slice of the form needed for the selected upload type ($_GET['section']).

$UploadForm = $Categories[$_GET['categoryid']];
$TorrentForm = new TORRENT_FORM();
$emitJS = isset($_GET['js']);

switch ($UploadForm) {
    case 'Music':
        if ($emitJS) {
            echo $TorrentForm->albumReleaseJS();
        } else {
            $tagMan = new Gazelle\Manager\Tag;
            $TorrentForm->music_form($tagMan->genreList());
        }
        break;

    case 'Audiobooks':
    case 'Comedy':
        if ($emitJS) {
            echo $TorrentForm->albumReleaseJS();
        } else {
            $TorrentForm->audiobook_form();
        }
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        if ($emitJS) {
            echo $TorrentForm->descriptionJS();
        } else {
            $TorrentForm->simple_form($_GET['categoryid']);
        }
        break;
    default:
        echo 'Invalid action!';
}
