<?php

$details = $_GET['details'] ?? 'all';
$details = in_array($_GET['details'] ?? '', ['top_used', 'top_request', 'top_voted'])
    ? $details : 'all';

$limit = $_GET['limit'] ?? 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

$tag = new Gazelle\Manager\Tag();

View::show_header(TOP_TEN_HEADING . " – Tags");
?>
<div class="thin">
    <div class="header">
        <h2><?= TOP_TEN_HEADING ?> – Tags</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'tags']) ?>
    </div>

<?php
if (in_array($details, ['all', 'top_used'])) {
    echo $Twig->render('top10/tag.twig', [
        'caption' => 'Most Used Torrent Tags',
        'detail'  => 'top_used',
        'list'    => $tag->topTGroupList($limit),
        'limit'   => $limit,
    ]);
}

if (in_array($details, ['all', 'top_request'])) {
    echo $Twig->render('top10/tag.twig', [
        'caption' => 'Most Used Request Tags',
        'detail'  => 'top_request',
        'list'    => $tag->topTGroupList($limit),
        'limit'   => $limit,
    ]);
}

if (in_array($details, ['all', 'top_voted'])) {
    echo $Twig->render('top10/tag.twig', [
        'caption' => 'Most Highly Voted Tags',
        'detail'  => 'top_voted',
        'list'    => $tag->topTGroupList($limit),
        'limit'   => $limit,
    ]);
}
?>
</div>
<?php View::show_footer() ?>
