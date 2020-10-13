<?php
$attrTypes = ['tags', 'folders', 'files', 'lineage'];
$filters = ['all', 'snatched', 'uploaded'];
$types = ['checksum', 'tags', 'folders', 'files', 'lineage', 'artwork', 'artistimg', 'artistdesc', 'artistdiscogs'];

if (!empty($_GET['userid']) && is_number($_GET['userid'])) {
    if (check_perms('users_override_paranoia')) {
        $userId = $_GET['userid'];
    } else {
        error(403);
    }
} else {
    $userId = $LoggedUser['ID'];
}

$filter = in_array($_GET['filter'] ?? '', $filters) ? $_GET['filter'] : $filters[0];
$type = in_array($_GET['type'] ?? '', $types) ? $_GET['type'] : $types[0];
$search = $_GET['search'] ?? '';

$better = new \Gazelle\Manager\Better($ReleaseTypes);

if (check_perms('admin_reports') && in_array($type, $attrTypes) && $remove = (int)($_GET['remove'] ?? 0)) {
    $better->removeAttribute($type, $remove);
}

[$page, $limit, $offset] = \Gazelle\DB::pageLimit(TORRENTS_PER_PAGE);

[$results, $resultCount, $mode] = $better->missing($type, $filter, $search, $limit, $offset, $userId);

$pages = Format::get_pages($page, $resultCount, TORRENTS_PER_PAGE);

View::show_header('Missing Search');

function selected($val) {
    return $val ? ' selected="selected"' : '';
}

$filters = array_reduce($filters, function ($acc, $item) use ($filter) {
    $acc[$item] = $item === $filter;
    return $acc;
}, []);

$types = array_reduce($types, function ($acc, $item) use ($type) {
    $acc[$item] = $item === $type;
    return $acc;
}, []);

switch ($mode) {
    case 'artists':
        $results = array_column($results, 'Name', 'ArtistID');
        break;
    case 'groups':
        $results = array_map(function ($item) {
            if (count($item['Artists']) > 1) {
                $artist = 'Various Artists';
            } else {
                $artist = sprintf('<a href="artist.php?id=%s" target="_blank">%s</a>', $item['Artists'][0]['id'], $item['Artists'][0]['name']);
            }

            return ['artist' => $artist, 'name' => $item['Name']];
        }, $results);
        break;
    case 'torrents':
        $results = $better->twigGroups($results);
        break;
}

echo G::$Twig->render('better/missing.twig', [
    'mode'           => $mode,
    'results'        => $results,
    'result_count'   => $resultCount,
    'filters'        => $filters,
    'search'         => $search,
    'types'          => $types,
    'auth_key'       => $LoggedUser['AuthKey'],
    'torrent_pass'   => $LoggedUser['torrent_pass'],
    'torrent_ids'    => $mode !== 'torrents' ? null : implode(',', array_keys($results)),
    'pages'          => $pages,
    'perms'          => [
        'zip_downloader' => check_perms('zip_downloader'),
        'admin_reports'  => check_perms('admin_reports'),
    ],
]);

View::show_footer();
