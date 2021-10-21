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

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

authorize();

$TorrentID = (int)$_POST['torrentid'];
if (!$TorrentID) {
    echo 'No Torrent ID';
    die();
}

if (!isset($_POST['type'])) {
    echo 'Missing Type';
    die();
}

[$CategoryID, $UserID] = $DB->row("
    SELECT tg.CategoryID, t.UserID
    FROM torrents_group AS tg
    INNER JOIN torrents AS t ON (t.GroupID = tg.ID)
    WHERE t.ID = ?
    ", $TorrentID
);
if (is_null($CategoryID)) {
    echo 'No torrent with that ID exists!';
    die();
}

$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();
if (array_key_exists($_POST['type'], $Types[$CategoryID])) {
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
        ", $TorrentID, $Viewer->id()
    )
) {
    die();
}

$DB->prepared_query("
    INSERT INTO reportsv2
           (ReporterID, TorrentID, Type, UserComment, ExtraID)
    VALUES (?,          ?,         ?,    ?,           ?)
    ", $Viewer->id(), $TorrentID, $Type, $Extra, $ExtraID
);
$ReportID = $DB->inserted_id();

if ($UserID != $Viewer->id()) {
    (new Gazelle\Manager\User)->sendPM($UserID, 0,
        "One of your torrents has been reported",
        $Twig->render('reportsv2/new.twig', [
            'id'     => $TorrentID,
            'title'  => $ReportType['title'],
            'reason' => $Extra,
        ])
    );
}

$Cache->delete_value("reports_torrent_$TorrentID");
$Cache->increment('num_torrent_reportsv2');

echo $ReportID;
