<?php

// echo out the slice of the form needed for the selected upload type ($_GET['section']).

$uploadForm = new Gazelle\Upload($Viewer);
$emitJS = isset($_GET['js']);

switch (CATEGORY[(int)$_GET['categoryid']]) {
    case 'Applications':
        echo $emitJS ? $uploadForm->descriptionJS() : $uploadForm->application();
        break;

    case 'Audiobooks':
        echo $emitJS ? $uploadForm->albumReleaseJS() : $uploadForm->audiobook();
        break;

    case 'Comedy':
        echo $emitJS ? $uploadForm->albumReleaseJS() : $uploadForm->comedy();
        break;

    case 'Comics':
        echo $emitJS ? $uploadForm->descriptionJS() : $uploadForm->comic();
        break;

    case 'E-Books':
        echo $emitJS ? $uploadForm->descriptionJS() : $uploadForm->ebook();
        break;

    case 'E-Learning Videos':
        echo $emitJS ? $uploadForm->descriptionJS() : $uploadForm->elearning();
        break;

    case 'Music':
        echo $emitJS
            ? $uploadForm->albumReleaseJS()
            : $uploadForm->music((new Gazelle\Manager\Tag())->genreList(), new Gazelle\Manager\TGroup());
        break;

    default:
        echo 'Invalid action!';
}
