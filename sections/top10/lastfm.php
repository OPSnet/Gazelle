<?php

$category = $_GET['category'] ?? 'weekly';
$category = in_array($category, ['all_time', 'weekly']) ? $category : 'weekly';

$view = $_GET['view'] ?? 'tiles';
$view = in_array($view, ['tiles', 'list']) ? $view : 'list';

echo $Twig->render('top10/lastfm.twig', [
    'artist_list' => $category === 'weekly' ? (new Gazelle\Util\LastFM())->weeklyArtists() : [],
    'category'    => $category,
    'view'        => $view,
]);
