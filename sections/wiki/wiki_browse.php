<?php

$wikiMan = new Gazelle\Manager\Wiki;

$letter = $_GET['letter'] ? strtoupper(substr($_GET['letter'], 0, 1)) : null;
if ($letter && $letter !== '1') {
    View::show_header('Wiki &rsaquo; Table of contents');
} else {
    View::show_header("Wiki &rsaquo; Articles &rsaquo; $letter");
}

echo $Twig->render('wiki/browse.twig', [
    'articles' => $wikiMan->articles($LoggedUser['EffectiveClass'], $letter),
    'letter'   => $letter,
]);

View::show_footer();
