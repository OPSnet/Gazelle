<?php
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$top10 = new Top10\User();

echo $Twig->render('top10/user.twig', [
    'detail' => $_GET['details'] ?? 'all',
    'limit'  => $_GET['limit'] ?? 10,
    'top10'  => $top10,
    'table'  => [
        'ul' => [
            'Title' => 'Uploaders',
            'Type'  => Enum\UserTop10Key::uploader,
        ],
        'dl' => [
            'Title' => 'Downloaders',
            'Type'  => Enum\UserTop10Key::downloader,
        ],
        'numul' => [
            'Title' => 'Torrents Uploaded',
            'Type'  => Enum\UserTop10Key::uploadTotal,
        ],
        'rv' => [
            'Title' => 'Request Votes',
            'Type'  => Enum\UserTop10Key::requestVote,
        ],
        'rf' => [
            'Title' => 'Request Fills',
            'Type'  => Enum\UserTop10Key::requestFill,
        ],
        'uls' => [
            'Title' => 'Fastest Uploaders',
            'Type'  => Enum\UserTop10Key::uploadSpeed,
        ],
        'dls' => [
            'Title' => 'Fastest Downloaders',
            'Type'  => Enum\UserTop10Key::downloadSpeed,
        ],
    ],
]);
