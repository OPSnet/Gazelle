<?php

$top10 = new Gazelle\Top10\User;
$tables = [
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
];

$details = $_GET['details'] ?? 'all';
$details = isset($tables[$details]) ? $details : 'all';

$limit = $_GET['limit'] ?? 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

View::show_header(TOP_TEN_HEADING . " – Users");
?>
<div class="thin">
    <div class="header">
        <h2><?= TOP_TEN_HEADING ?> – Users</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'users']) ?>
    </div>
<?php

foreach ($tables as $tag => $table) {
    if ($details === 'all' || $details === $tag) {
        echo($Twig->render('top10/users.twig', [
            'results' => $top10->fetch($table['Type'], $limit),
            'limit'   => $limit,
            'tag'     => $tag,
            'title'   => $table['Title']
        ]));
    }
}

echo '</div>';
View::show_footer();
