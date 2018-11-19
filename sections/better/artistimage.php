<?php

View::show_header('Artists with no image');

$DB->query("
SELECT COUNT(*) as count FROM artists_group AS a
LEFT JOIN wiki_artists AS wiki ON wiki.RevisionID = a.RevisionID
WHERE wiki.Image is NULL OR wiki.Image = ''");
$row = $DB->next_record();
$total = $row['count'];
$total_str = number_format($total);
$page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * ($page-1);
$DB->query("
SELECT
    a.ArtistID,
    a.Name
FROM artists_group AS a
    LEFT JOIN wiki_artists AS wiki ON wiki.RevisionID = a.RevisionID
WHERE wiki.Image is NULL OR wiki.Image = ''
ORDER BY a.Name
LIMIT {$limit} OFFSET {$offset}");
$artists = $DB->to_array('ArtistID', MYSQLI_ASSOC);
$pages = Format::get_pages($page, $total, TORRENTS_PER_PAGE);
print <<<HTML
<div class="header">
    <h2>Artists that are missing images</h2>
    
    <div class="linkbox">
        <a href="better.php" class="brackets">Back to better.php list</a>
    </div>
    <div class="linkbox">{$pages}</div>
</div>

<div class="thin box pad">
    <h3>There are {$total_str} artists remaining</h3>
    <table class="torrent_table">
HTML;

foreach ($artists as $id => $artist) {
    print <<<HTML
        <tr class="torrent torrent_row">
            <td><a href='artist.php?id={$id}' target='_blank'>{$artist['Name']}</a></td>
        </tr>
HTML;
}
print <<<HTML
    </table>
</div>
HTML;

View::show_footer();
