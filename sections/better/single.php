<?php

echo $Twig->render('better/single.twig', [
    'results' => (new Gazelle\Manager\Better(new Gazelle\ReleaseType))->singleSeeded($Viewer),
    'viewer'  => $Viewer,
]);
