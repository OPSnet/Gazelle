<?php

// echo out the slice of the form needed for the selected upload type ($_GET['section']).

$uploadForm = new Gazelle\Upload($Viewer);
$uploadForm->setCategoryId((int)$_GET['categoryid'] + 1);
$emitJS = isset($_GET['js']);

switch (CATEGORY[$_GET['categoryid']]) {
    case 'Music':
        if ($emitJS) {
            echo $uploadForm->albumReleaseJS();
        } else {
            echo $uploadForm->music_form(
                (new Gazelle\Manager\Tag())->genreList(),
                new Gazelle\Manager\TGroup(),
            );
        }
        break;

    case 'Audiobooks':
    case 'Comedy':
        if ($emitJS) {
            echo $uploadForm->albumReleaseJS();
        } else {
            echo $uploadForm->audiobook_form();
        }
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        if ($emitJS) {
            echo $uploadForm->descriptionJS();
        } else {
            echo $uploadForm->simple_form();
        }
        break;
    default:
        echo 'Invalid action!';
}
