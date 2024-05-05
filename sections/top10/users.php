<?php

$top10 = new Gazelle\Top10\User();

echo $Twig->render('top10/user.twig', [
    'detail' => $_GET['details'] ?? 'all',
    'limit'  => $_GET['limit'] ?? 10,
    'top10'  => $top10,
    'table'  => [
        'ul' => [
            'Title' => 'Uploaders',
            'Type' => $top10::UPLOADERS
        ],
        'dl' => [
            'Title' => 'Downloaders',
            'Type' => $top10::DOWNLOADERS
        ],
        'numul' => [
            'Title' => 'Torrents Uploaded',
            'Type' => $top10::UPLOADS
        ],
        'rv' => [
            'Title' => 'Request Votes',
            'Type' => $top10::REQUEST_VOTES
        ],
        'rf' => [
            'Title' => 'Request Fills',
            'Type' => $top10::REQUEST_FILLS
        ],
        'uls' => [
            'Title' => 'Fastest Uploaders',
            'Type' => $top10::UPLOAD_SPEED
        ],
        'dls' => [
            'Title' => 'Fastest Downloaders',
            'Type' => $top10::DOWNLOAD_SPEED
        ],
    ],
]);
