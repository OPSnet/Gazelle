<?php
/*
 * This page is for creating a report using AJAX.
 * It should have the following posted fields:
 *     [auth] => AUTH_KEY
 *    [torrentid] => TORRENT_ID
 *    [type] => TYPE
 *    [otherid] => OTHER_ID
 *
 * It should not be used on site as is, except in its current use (Switch) as it is lacking for any purpose but this.
 */

if (!check_perms('admin_reports')) {
    error(403);
}

authorize();

if ((int)$_POST['torrentid'] < 1) {
    echo 'No Torrent ID';
    die();
} else {
    $TorrentID = (int)$_POST['torrentid'];
}

$CategoryID = $DB->scalar("
    SELECT tg.CategoryID
    FROM torrents_group AS tg
    INNER JOIN torrents AS t ON (t.GroupID = tg.ID)
    WHERE t.ID = ?
    ", $TorrentID
);
if (!$CategoryID) {
    echo 'No torrent with that ID exists!';
    die();
}

if (!isset($_POST['type'])) {
    echo 'Missing Type';
    die();
} elseif (array_key_exists($_POST['type'], $Types[$CategoryID])) {
    $Type = $_POST['type'];
    $ReportType = $Types[$CategoryID][$Type];
} elseif (array_key_exists($_POST['type'], $Types['master'])) {
    $Type = $_POST['type'];
    $ReportType = $Types['master'][$Type];
} else {
    //There was a type but it wasn't an option!
    echo 'Wrong type';
    die();
}

$ExtraID = (int)$_POST['otherid'];

if (empty($_POST['extra'])) {
    $Extra = '';
} else {
    $Extra = trim($_POST['extra']);
}

if ($DB->scalar("
    SELECT ID
    FROM reportsv2
    WHERE
        ReportedTime > now() - INTERVAL 5 SECOND
        AND TorrentID = ?
        AND ReporterID = ?
        ", $TorrentID, $LoggedUser['ID']
    )
) {
    die();
}

$DB->prepared_query("
    INSERT INTO reportsv2
           (ReporterID, TorrentID, Type, UserComment, ExtraID)
    VALUES (?,          ?,         ?,    ?,           ?)
    ", $LoggedUser['ID'], $TorrentID, $Type, $Extra, $ExtraID
);
$ReportID = $DB->inserted_id();

if ($UserID != $LoggedUser['ID']) {
    Misc::send_pm($UserID, 0, "One of your torrents has been reported",
        G::$Twig->render('reportsv2/new.twig', [
            'id'    => $TorrentID,
            'title' => $ReportType['title'],
        ])
    );
}

$Cache->delete_value("reports_torrent_$TorrentID");
$Cache->increment('num_torrent_reportsv2');

echo $ReportID;
