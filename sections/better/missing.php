<?php
$badMap = [
    'tags' => 'torrents_bad_tags',
    'folders' => 'torrents_bad_folders',
    'files' => 'torrents_bad_files',
    'lineage' => 'torrents_missing_lineage'
];

if (!empty($_GET['userid']) && is_number($_GET['userid'])) {
    if (check_perms('users_override_paranoia')) {
        $userId = $_GET['userid'];
    } else {
        error(403);
    }
} else {
    $userId = $LoggedUser['ID'];
}

if (empty($_GET['type']) || !in_array($_GET['type'], ['checksum', 'tags', 'folders', 'files', 'lineage', 'artwork', 'artistimg', 'artistdesc'])) {
    $_GET['type'] = 'checksum';
}
$type = $_GET['type'];

if (empty($_GET['filter']) || !in_array($_GET['filter'], ['snatched', 'uploaded'])) {
    $_GET['filter'] = 'all';
}
$filter = $_GET['filter'];

if (empty($_GET['search'])) {
    $_GET['search'] = '';
}
$search = $_GET['search'];

if (check_perms('admin_reports') && in_array($type, array_keys($badMap)) && !empty($_GET['remove']) && is_number($_GET['remove'])) {
    $remove = $_GET['remove'];
    $DB->prepared_query(sprintf('
        DELETE FROM %s
        WHERE TorrentID = ?', $badMap[$type]), $remove);
    $DB->prepared_query('
        SELECT GroupID
        FROM torrents
        WHERE ID = ?', $remove);
    list($groupId) = $DB->next_record();
    $Cache->delete_value('torrents_details_'.$groupId);
}

$query = '';
$joins = [];
$where = [];
$order = '';
$params = [];
$joinparams = [];

switch ($type) {
    case 'checksum':
        $query = '
            SELECT SQL_CALC_FOUND_ROWS t.ID AS TorrentID, t.GroupID
            FROM torrents t
            INNER JOIN torrents_group tg ON tg.ID = t.GroupID';
        $order = 'ORDER BY t.Time ASC';
        $where[] = "t.HasLogDB = '1' AND t.LogChecksum = '0'";
        $mode = 'torrents';
        switch ($filter) {
            case 'snatched':
                $joins[] = 'INNER JOIN xbt_snatched as x ON x.fid = t.ID AND x.uid = ?';
                $joinparams[] = $userId;
                break;
            case 'uploaded':
                $where[]  = 't.UserID = ?';
                $params[] = $userId;
                break;
        }
        break;
    case 'tags':
    case 'folders':
    case 'files':
    case 'lineage':
        $query = sprintf('
            SELECT SQL_CALC_FOUND_ROWS bad.TorrentID, t.GroupID
            FROM %s AS bad
            INNER JOIN torrents t ON t.ID = bad.TorrentID
            INNER JOIN torrents_group tg ON tg.ID = t.GroupID', $badMap[$type]);
        $order = 'ORDER BY bad.TimeAdded ASC';
        $mode = 'torrents';
        switch ($filter) {
            case 'snatched':
                $joins[] = 'INNER JOIN xbt_snatched as x ON x.fid = bad.TorrentID AND x.uid = ?';
                $joinparams[] = $userId;
                break;
            case 'uploaded':
                $where[] = 't.UserID = ?';
                $params[] = $userId;
                break;
        }
        break;
    case 'artwork':
        $query = '
            SELECT SQL_CALC_FOUND_ROWS tg.ID, tg.Name
            FROM torrents_group tg';
        $where[] = "tg.CategoryID = 1 AND coalesce(wt.Image, tg.WikiImage) = ''";
        $order = 'ORDER BY tg.Name';
        $joins[] = 'LEFT JOIN wiki_torrents wt ON wt.RevisionID = tg.RevisionID';
        $mode = 'groups';
        break;
    case 'artistimg':
        $query = '
            SELECT SQL_CALC_FOUND_ROWS a.ArtistID, a.Name
            FROM artists_group a';
        $where[] = "(wa.Image IS NULL OR wa.Image = '')";
        $order = 'ORDER BY a.Name';
        $joins[] = 'LEFT JOIN wiki_artists wa ON wa.RevisionID = a.RevisionID';
        $mode = 'artists';
        break;
    case 'artistdesc':
        $query = '
            SELECT SQL_CALC_FOUND_ROWS a.ArtistID, a.Name
            FROM artists_group a';
        $where[] = "(wa.Body IS NULL OR wa.Body = '')";
        $order = 'ORDER BY a.Name';
        $joins[] = 'LEFT JOIN wiki_artists wa ON wa.RevisionID = a.RevisionID';
        $mode = 'artists';
        break;
}

if ($search !== '') {
    switch ($mode) {
        case 'torrents':
            $where[] = '(
                    tg.Name LIKE ?
                OR  t.Description LIKE ?
                OR  coalesce(wt.Body, tg.WikiBody) LIKE ?
                OR  tg.TagList LIKE ?
            )';
            $searchString = "%$search%";
            $params = array_merge($params, array_fill(0, 4, $searchString));
            $joins[] = 'LEFT JOIN wiki_torrents wt ON wt.RevisionID = tg.RevisionID';
            break;
        case 'groups':
            $where[] = '(
                    tg.Name LIKE ?
                OR  coalesce(wt.Body, tg.WikiBody) LIKE ?
                OR  tg.TagList LIKE ?
            )';
            $searchString = "%$search%";
            $params = array_merge($params, array_fill(0, 3, $searchString));
            break;
        case 'artists':
            $where[] = 'a.Name LIKE ?';
            $searchString = "%$search%";
            $params = array_merge($params, [$searchString]);
            break;
    }
}

if (count($where) > 0) {
    $where = 'WHERE '.implode(' AND ', $where);
} else {
    $where = '';
}

$page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * ($page - 1);

$joins = implode("\n", $joins);
$params = array_merge($joinparams, $params);
$query = sprintf('
%s
%s
%s
%s LIMIT %s OFFSET %s', $query, $joins, $where, $order, $limit, $offset);

$qId = $DB->prepared_query($query, ...$params);
$DB->prepared_query('SELECT FOUND_ROWS()');
list($resultCount) = $DB->next_record();

$pages = Format::get_pages($page, $resultCount, TORRENTS_PER_PAGE);

switch ($mode) {
    case 'torrents':
        if ($resultCount > 0) {
            $DB->set_query_id($qId);
            $torrents = $DB->to_array('TorrentID', MYSQLI_ASSOC);
        } else {
            $torrents  = [];
        }
        $groups = array_map(function ($t) { return $t['GroupID']; }, $torrents);
        $results = Torrents::get_groups($groups);
        break;
    case 'groups':
        if ($resultCount > 0) {
            $DB->set_query_id($qId);
            $groups = $DB->to_array('ID', MYSQLI_ASSOC);
            foreach (Artists::get_artists(array_keys($groups)) as $groupId => $data) {
                $groups[$groupId]['Artists'] = [];
                $groups[$groupId]['ExtendedArtists'] = [];
                foreach ([1, 4, 6] as $importance) {
                    if (isset($data[$importance])) {
                        $groups[$groupId]['Artists'] = array_merge($groups[$groupId]['Artists'], $data[$importance]);
                    }
                }
            }
        } else {
            $groups  = [];
        }
        break;
    case 'artists':
        if ($resultCount > 0) {
            $DB->set_query_id($qId);
            $artists = $DB->to_array('ArtistID', MYSQLI_ASSOC);
        } else {
            $artists = [];
        }
        break;
}

View::show_header('Missing Search');

function selected($val) {
    return $val ? ' selected="selected"' : '';
}
?>

<br />
<div class="thin">
    <h2>Missing</h2>
    <div class="linkbox">
        <a class="brackets" href="better.php?method=transcode">Transcodes</a>
        <a class="brackets" href="better.php?method=missing">Missing</a>
        <a class="brackets" href="better.php?method=single">Single Seeded</a>
    </div>
    <form class="search_form" name="missing" action="" method="get">
        <input type="hidden" name="method" value="missing" />
        <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
            <tr>
                <td class="label"><strong>Filter</strong></td>
                <td>
                    <select name="type">
                        <option value="checksum"<?=selected($type == 'checksum')?>>Missing Checksums</option>
                        <option value="tags"<?=selected($type == 'tags')?>>Bad Tags</option>
                        <option value="folders"<?=selected($type == 'folders')?>>Bad Folders</option>
                        <option value="files"<?=selected($type == 'files')?>>Bad Files</option>
                        <option value="lineage"<?=selected($type == 'lineage')?>>Missing Lineage</option>
                        <option value="artwork"<?=selected($type == 'artwork')?>>Missing Artwork</option>
                        <option value="artistimg"<?=selected($type == 'artistimg')?>>Missing Artist Images</option>
                        <option value="artistdesc"<?=selected($type == 'artistdesc')?>>Missing Artist Descriptions</option>
                    </select>
                    <select name="filter">
                        <option value="all"<?=selected($filter == 'all')?>>All</option>
                        <option value="snatched"<?=selected($filter == 'snatched')?>>Snatched</option>
                        <option value="uploaded"<?=selected($filter == 'uploaded')?>>Uploaded</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Search</strong></td>
                <td>
                    <input type="search" name="search" size="60" value="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : '')?>" />
                </td>
            </tr>
            <tr><td>&nbsp;</td><td><input type="submit" value="Search" /></td></tr>
        </table>
    </form>
    <div class="linkbox">
        <?=$pages?>
    </div>
    <div class="box pad">
        <div class="torrent">
            <h3>There are <?=$resultCount?> <?=$mode?> remaining<?php
if ($mode == 'torrents' && count($torrents) > 1 && check_perms('zip_downloader')) {
    $idList = implode(',', array_map(function ($t) { return $t['TorrentID']; }, $torrents));
?>
                <span class="torrents_links_block">
                <a class="brackets" href="torrents.php?action=collector&amp;title=better&amp;ids=<?=$idList?>" onclick="return confirm('If you do not have the content, your ratio WILL be affected; be sure to check the size of all torrents before downloading.');">Download All</a>
                </span>
            </h3>
        </div>
<?php
}
?>
        <table width"=100%" class="torrent_table">
<?php
switch ($mode) {
    case 'torrents':
        foreach ($torrents as $torrent => $info) {
            $group = $results[$info['GroupID']];
            $groupId = $group['ID'];
            $groupYear = $group['Year'];
            $groupName = $group['Name'];
            $groupFlags = isset($group['Flags']) ? $group['Flags'] : ['IsSnatched' => false];
            $groupTorrents = isset($group['Torrents']) ? $group['Torrents'] : [];
            $releaseType = $group['ReleaseType'];
            $tags = new Tags($group['TagList']);
            $extendedArtists = $group['ExtendedArtists'];

            if (!empty($extendedArtists[1]) || !empty($extendedArtists[4]) || !empty($extendedArtists[5]) || !empty($extendedArtists[6])) {
                unset($extendedArtists[2]);
                unset($extendedArtists[3]);
                $displayName = Artists::display_artists($extendedArtists);
            } else {
                $displayName = '';
            }
            $displayName .= "<a href=\"torrents.php?id=$groupId&amp;torrentid=$torrent#torrent$torrent\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$groupName</a>";
            if ($groupYear > 0) {
                $displayName .= " [$groupYear]";
            }
            if ($releaseType > 0) {
                $displayName .= ' ['.$ReleaseTypes[$releaseType].']';
            }

            $extraInfo = Torrents::torrent_info($groupTorrents[$torrent]);
            if ($extraInfo) {
                $displayName .= " - $extraInfo";
            }
?>
                <tr class="torrent torrent_row<?=$groupFlags['IsSnatched'] ? ' snatched_torrent"' : ''?>">
                    <td>
                <span class="torrent_links_block">
                    <a href="torrents.php?action=download&amp;id=<?=$torrent?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="brackets tooltip" title="Download">DL</a>
                </span>
                        <?=$displayName?>
                        <?php  if (check_perms('admin_reports')) { ?>
                            <a href="better.php?method=missing&amp;type=<?=$type?>&amp;remove=<?=$torrent?>&amp;filter=<?=$filter?>&amp;search=<?=$search?>" class="brackets">X</a>
                        <?php  } ?>
                        <div class="tags"><?=$tags->format()?></div>
                    </td>
                </tr>
<?php
        }
        break;
    case 'groups':
        foreach ($groups as $id => $group) {
            if (count($group['Artists']) > 1) {
                $artist = 'Various Artists';
            } else {
                $artist = sprintf('<a href="artist.php?id=%s" target="_blank">%s</a>', $group['Artists'][0]['id'], $group['Artists'][0]['name']);
            }
?>
                <tr>
                    <td><?=$artist?> - <a href="torrents.php?id=<?=$id?>" target="_blank"><?=$group['Name']?></a></td>
                </tr>
<?php
        }
        break;
    case 'artists':
        foreach ($artists as $id => $artist) {
?>
                <tr>
                <td><a href="artist.php?id=<?=$id?>"><?=$artist['Name']?></a></td>
                </tr>
<?php
        }
        break;
}
?>
        </table>
    </div>
</div>

<?php
View::show_footer();
?>
