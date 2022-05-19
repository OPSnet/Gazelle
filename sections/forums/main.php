<?php

echo $Twig->render('forum/main.twig', [
    'toc'    => (new Gazelle\Manager\Forum())->tableOfContents($Viewer),
    'viewer' => $Viewer,
]);
