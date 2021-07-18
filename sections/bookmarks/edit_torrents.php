<?php

function artistName(array &$extended, array &$artists) {
    if (!empty($extended[1]) || !empty($extended[4]) || !empty($extended[5]) || !empty($extended[6])) {
        unset($extended[2], $extended[3]);
        return Artists::display_artists($extended, true, false);
    } elseif (count($artists) > 0) {
        return Artists::display_artists(['1' => $artists], true, false);
    }
    return '';
}

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
}
[, $CollageDataList, $TorrentList] = $user->bookmarkList(); // TODO: $TorrentList might not have the correct order, use the $GroupIDs instead

View::show_header('Organize Bookmarks', ['js' => 'browse,jquery-ui,jquery.tablesorter,sort']);

if (empty($TorrentList)) {
    echo $Twig->render('bookmark/none.twig');
} else {
    echo $Twig->render('bookmark/header.twig', [
        'heading' => 'Organize Torrent Bookmarks',
    ]);

    $list = [];
    foreach ($TorrentList as $groupId => $group) {
        if (!is_array($group['ExtendedArtists'])) {
            $group['ExtendedArtists'] = [];
        }
        if (!is_array($group['Artists'])) {
            $group['Artists'] = [];
        }
        $list[] = [
            'added'    => date($CollageDataList[$groupId]['Time']),
            'artist'   => artistName($group['ExtendedArtists'], $group['Artists']),
            'name'     => $group['Name'],
            'sequence' => $CollageDataList[$groupId]['Sort'],
            'group_id' => $groupId,
            'showcase' => $group['VanityHouse'],
            'year'     => $group['Year'] > 0 ? $group['Year'] : '',
        ];
    }

    echo $Twig->render('bookmark/body.twig', [
        'list' => $list,
    ]);

    echo $Twig->render('bookmark/footer.twig', [
        'auth'      => $Viewer->auth(),
        'edit_type' => $_GET['type'] ?? 'torrents',
    ]);
}

View::show_footer();
