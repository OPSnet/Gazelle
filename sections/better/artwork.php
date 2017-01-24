<?php

View::show_header('Torrents with no artwork');

$DB->query("SELECT COUNT(*) as count FROM torrents_group WHERE CategoryID = 1 AND WikiImage = ''");
$row = $DB->next_record();
$total = number_format($row['count']);
$page = max(0, isset($_GET['page']) ? (intval($_GET['page'])-1) : 0);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * $page;
$DB->query("
SELECT ID, Name
FROM torrents_group
WHERE CategoryID = 1 AND WikiImage = ''
ORDER BY Name
LIMIT {$limit} OFFSET {$offset}");
$torrents = $DB->to_array('ID', MYSQLI_ASSOC);
foreach (Artists::get_artists(array_keys($torrents)) as $group_id => $data) {
    $torrents[$group_id]['Artists'] = array();
    $torrents[$group_id]['ExtendedArtists'] = array();
    foreach(array(1, 4, 6) as $importance) {
        if (isset($data[$importance])) {
            $torrents[$group_id]['Artists'] = array_merge($torrents[$group_id]['Artists'], $data[$importance]);
        }
    }
}
$pages = Format::get_pages($offset+1, $total, TORRENTS_PER_PAGE);
print <<<HTML
<div class="header">
    <h2>Torrent groups that are missing artwork</h2>
    
    <div class="linkbox">
        <a href="better.php" class="brackets">Back to better.php list</a>
    </div>
    <div class="linkbox">{$pages}</div>
</div>

<div class="thin box pad">
    <h3>There are {$total} torrent groups remaining</h3>
    <table class="torrent_table">
HTML;

foreach ($torrents as $id => $torrent) {
    if (count($torrent['Artists']) > 1) {
        $artist = "Various Artists";
    }
    else {
        $artist = "<a href='artist.php?id={$torrent['Artists'][0]['id']}' target='_blank'>{$torrent['Artists'][0]['name']}</a>";
    }
    print <<<HTML
        <tr class="torrent torrent_row">
            <td>{$artist} - <a href="torrents.php?id={$id}" target="_blank">{$torrent['Name']}</a></td>
        </tr>
HTML;
}
print <<<HTML
    </table>
</div>
HTML;

View::show_footer();