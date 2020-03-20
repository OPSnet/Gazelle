<?php

authorize();

$collageID = $_POST['collageid'];
if (!is_number($collageID)) {
    error(404);
}

$DB->prepared_query("
    SELECT UserID, CategoryID
    FROM collages
    WHERE ID = ?",
    $collageID);
list($userID, $categoryID) = $DB->next_record();
if ($categoryID === 0 && $userID != $LoggedUser['ID'] && !check_perms('site_collages_delete')) {
    error(403);
}

function parse_args($args) {
    $arr = [];
    $pairs = explode('&', $args);

    foreach ($pairs as $i) {
        list($name, $value) = explode('=', $i, 2);

        if (isset($arr[$name])) {
            if (!is_array($arr[$name])) {
                $arr[$name] = [$arr[$name]];
            }
                $arr[$name][] = $value;
        } else {
            $arr[$name] = $value;
        }
    }

    return $arr;
}

$groupID = $_POST['groupid'];
if (!is_number($groupID)) {
    error(404);
}

if (isset($_POST['submit']) && $_POST['submit'] === 'Remove') {
    $DB->prepared_query("
        DELETE FROM collages_torrents
        WHERE CollageID = ?
            AND GroupID = ?",
        $collageID, $groupID);
    $rows = $DB->affected_rows();
    $DB->prepared_query("
        UPDATE collages
        SET NumTorrents = NumTorrents - ?
        WHERE ID = ?",
        $rows, $collageID);
    $Cache->delete_value("torrents_details_$groupID");
    $Cache->delete_value("torrent_collages_$groupID");
    $Cache->delete_value("torrent_collages_personal_$groupID");
} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    $series = parse_args($_POST['drag_drop_collage_sort_order']);
    $series = array_shift($series);
    if (is_array($series)) {
        $sql = array_fill(0, count($series), '(?, ?, ?)');
        $params = array_merge(...array_map(function ($sort, $groupID) use ($collageID) {
            return [$groupID, ($sort + 1) * 10, $collageID];
        }, array_keys($series), $series));

        $sql = '
            INSERT INTO collages_torrents
                (GroupID, sort, CollageID)
            VALUES
                ' . implode(', ', $sql) . '
            ON DUPLICATE KEY UPDATE
                sort = VALUES (sort)';

      $DB->prepared_query($sql, ...$params);
    }
} else {
    $sort = $_POST['sort'];
    if (!is_number($sort)) {
        error(404);
    }
    $DB->prepared_query("
        UPDATE collages_torrents
        SET sort = ?
        WHERE CollageID = ?
            AND GroupID = ?",
        $sort, $collageID, $groupID);
}

$Cache->delete_value("collage_$collageID");
header("Location: collages.php?action=manage&collageid=$collageID");
