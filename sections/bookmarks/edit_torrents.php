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
    $UserID = $LoggedUser['ID'];
} else {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $UserID = (int)$_GET['userid'];
    if (!$UserID) {
        error(404);
    }
}

[, $CollageDataList, $TorrentList] = Users::get_bookmarks($UserID); // TODO: $TorrentList might not have the correct order, use the $GroupIDs instead

View::show_header('Organize Bookmarks', 'browse,jquery-ui,jquery.tablesorter,sort');

if (empty($TorrentList)) {
    echo G::$Twig->render('bookmark/none.twig');
} else {
    echo G::$Twig->render('bookmark/header.twig', [
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

    echo G::$Twig->render('bookmark/body.twig', [
        'list' => $list,
    ]);

    echo G::$Twig->render('bookmark/footer.twig', [
        'auth'      => $LoggedUser['AuthKey'],
        'edit_type' => $_GET['type'] ?? 'torrents',
    ]);
}

View::show_footer();
