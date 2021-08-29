<?php
// echo out the slice of the form needed for the selected upload type ($_GET['section']).

$uploadForm = new Gazelle\Util\UploadForm($Viewer);
$emitJS = isset($_GET['js']);

switch (CATEGORY[$_GET['categoryid']]) {
    case 'Music':
        if ($emitJS) {
            echo $uploadForm->albumReleaseJS();
        } else {
            $tagMan = new Gazelle\Manager\Tag;
            $uploadForm->music_form($tagMan->genreList());
        }
        break;

    case 'Audiobooks':
    case 'Comedy':
        if ($emitJS) {
            echo $uploadForm->albumReleaseJS();
        } else {
            $uploadForm->audiobook_form();
        }
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        if ($emitJS) {
            echo $uploadForm->descriptionJS();
        } else {
            $uploadForm->simple_form($_GET['categoryid']);
        }
        break;
    default:
        echo 'Invalid action!';
}
