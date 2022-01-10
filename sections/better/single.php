<?php

echo $Twig->render('better/single.twig', [
    'results' => (new Gazelle\Manager\Better(new Gazelle\ReleaseType))->singleSeeded($Viewer),
    'snatcher' => new \Gazelle\User\Snatch($Viewer),
    'viewer'  => $Viewer,
]);
