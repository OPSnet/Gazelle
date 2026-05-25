<?php

declare(strict_types=1);

namespace Gazelle;

header('Content-Type: application/json');
header('Cache-Control: private, max-age=3600');

$prefix = trim(urldecode($_GET['query'] ?? ''));
$twig = Util\Twig::factory(new Manager\User());
$suggestions = array_map(
    fn ($entry) => [
        'data'  => $entry['data'],
        'value' => $entry['value'],
        'html'  => $twig->render('artist/widget.twig', $entry['widget']),
    ],
    (new Manager\Artist())->autocompleteList($prefix)
);

echo json_encode([
    'query'       => $prefix,
    'suggestions' => $suggestions,
]);
