<?php
/** @phpstan-var \Twig\Environment $Twig */

$detail = $_GET['details'] ?? 'all';
$limit  = $_GET['limit'] ?? 10;

echo $Twig->render('top10/tag.twig', [
    'detail'  => in_array($detail, ['all', 'top_used', 'top_request', 'top_voted']) ? $detail : 'all',
    'limit'   => in_array($limit, [10, 100, 250]) ? $limit : 10,
    'manager' => new Gazelle\Manager\Tag(),
]);
