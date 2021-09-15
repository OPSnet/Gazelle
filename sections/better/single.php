<?php

echo $Twig->render('better/single.twig', [
    'results' => (new Gazelle\Manager\Better(new Gazelle\ReleaseType))->singleSeeded($Viewer->id()),
    'viewer'  => $Viewer,
]);
