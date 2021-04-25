<?php

$wikiMan = new Gazelle\Manager\Wiki;

$title = 'Browse wiki articles';
$letter = $_GET['letter'] ? strtoupper(substr($_GET['letter'], 0, 1)) : null;
if ($letter && $letter !== '1') {
    $title .= " ($letter)";
}

View::show_header($title);

echo $Twig->render('wiki/browse.twig', [
    'title' => $title,
    'articles' => $wikiMan->articles($LoggedUser['EffectiveClass'], $letter),
]);

View::show_footer();
