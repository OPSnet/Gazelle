<?php
$top10 = new \Gazelle\Top10\User;

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
$details = in_array($details, $tables) ? $details : 'all';

$limit = $_GET['limit'] ?? 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

View::show_header('Top 10 Users');
?>
<div class="thin">
    <div class="header">
        <h2>Top 10 Users</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'users']) ?>
    </div>
<?php

foreach ($tables as $tag => $table) {
    if ($details === 'all' || $details === $tag) {
        $results = $top10->fetch($table['Type'], $limit);
        $rank = 0;
        foreach ($results as &$result) {
            $result['username'] = Users::format_username($result['id'], false, false, false);
            $result['num_uploads'] = number_format($result['num_uploads']);
            $result['request_fills'] = number_format($result['request_fills']);
            $result['ratio'] = Format::get_ratio_html($result['uploaded'], $result['downloaded']);
            $result['join_date'] = time_diff($result['join_date']);
            $result['rank'] = ++$rank;
            foreach (['uploaded', 'up_speed', 'downloaded', 'down_speed', 'request_votes'] as $key) {
                $result[$key] = Format::get_size($result[$key]);
            }
        }
        unset($result);

        echo($Twig->render('top10/users.twig', [
            'results' => $results,
            'limit'   => $limit,
            'tag'     => $tag,
            'title'   => $table['Title']
        ]));
    }
}

echo '</div>';
View::show_footer();
