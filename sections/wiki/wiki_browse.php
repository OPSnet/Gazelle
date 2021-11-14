<?php

$letter = isset($_GET['letter']) ? strtoupper(substr($_GET['letter'], 0, 1)) : null;

echo $Twig->render('wiki/browse.twig', [
    'articles' => (new Gazelle\Manager\Wiki)->articles($Viewer->effectiveClass(), $letter),
    'letter'   => $letter,
]);
