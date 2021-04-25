<?php
/*
 * This page handles the backend from when a user submits a report.
 * It checks for (in order):
 * 1. The usual POST injections, then checks that things.
 * 2. Things that are required by the report type are filled
 *     ('1' in the report_fields array).
 * 3. Things that are filled are filled with correct things.
 * 4. That the torrent you're reporting still exists.
 *
 * Then it just inserts the report to the DB and increments the counter.
 */

authorize();

if ((int)$_POST['torrentid'] < 1 || (int)$_POST['categoryid'] < 1) {
    error(404);
}
$TorrentID = (int)$_POST['torrentid'];
$CategoryID = (int)$_POST['categoryid'];

$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();
if (!isset($_POST['type'])) {
    error(404);
} elseif (array_key_exists($_POST['type'], $Types[$CategoryID])) {
    $Type = $_POST['type'];
    $ReportType = $Types[$CategoryID][$Type];
} elseif (array_key_exists($_POST['type'], $Types['master'])) {
    $Type = $_POST['type'];
    $ReportType = $Types['master'][$Type];
} else {
    //There was a type but it wasn't an option!
    error(403);
}

foreach ($ReportType['report_fields'] as $Field => $Value) {
    if ($Value == '1') {
        if (empty($_POST[$Field])) {
            $Err = "You are missing a required field ($Field) for a " . $ReportType['title'] . ' report.';
        }
    }
}

if (empty($_POST['sitelink'])) {
    $ExtraIDs = '';
} else {
    if (preg_match_all('/'.TORRENT_REGEX.'/i', $_POST['sitelink'], $Matches)) {
        $Match = end($Matches);
        $ExtraIDs = implode(' ', $Match);
        if (in_array($TorrentID, $Match)) {
            $Err = "The extra permalinks you gave included the link to the torrent you're reporting!";
        }
    } else {
        $Err = 'The permalink was incorrect. Please copy the torrent permalink URL, which is labelled as [PL] and is found next to the [DL] buttons.';
    }
}

if (empty($_POST['link'])) {
    $Links = '';
} else {
    //resource_type://domain:port/filepathname?query_string#anchor
    //                    http://        www            .foo.com                                /bar
    if (preg_match_all('/'.URL_REGEX.'/is', $_POST['link'], $Matches)) {
        $Links = implode(' ', $Matches[0]);
    } else {
        $Err = "The extra links you provided weren't links...";
    }
}

if (empty($_POST['image'])) {
    $Images = '';
} else {
    if (preg_match("/^(".IMAGE_REGEX.")( ".IMAGE_REGEX.")*$/is", trim($_POST['image']), $Matches)) {
        $Images = $Matches[0];
    } else {
        $Err = "The extra image links you provided weren't links to images...";
    }
}

if (empty($_POST['track'])) {
    $Tracks = '';
} else {
    if (preg_match('/([0-9]+( [0-9]+)*)|All/is', $_POST['track'], $Matches)) {
        $Tracks = $Matches[0];
    } else {
        $Err = 'Tracks should be given in a space-separated list of numbers with no other characters.';
    }
}

$userComment = trim($_POST['extra']);
if (empty($userComment)) {
    $Err = 'As useful as blank reports are, could you be a tiny bit more helpful? (Leave a comment)';
}

list($GroupID, $UserID) = $DB->row("
    SELECT GroupID, UserID
    FROM torrents
    WHERE ID = ?
    ", $TorrentID
);
if (!$GroupID) {
    $Err = "A torrent with that ID doesn't exist!";
}

if ($DB->scalar("
    SELECT ID
    FROM reportsv2
    WHERE
        ReportedTime > now() - INTERVAL 5 SECOND
        AND TorrentID = ?
        AND ReporterID = ?
        ", $TorrentID, $LoggedUser['ID']
)) {
    $Err = "Slow down, you're moving too fast!";
}

if (!empty($Err)) {
    error($Err);
}

$DB->prepared_query("
    INSERT INTO reportsv2
           (ReporterID, TorrentID, Type, UserComment, Track, Image, ExtraID, Link)
    VALUES (?,          ?,         ?,    ?,           ?,     ?,     ?,       ?)
    ", $LoggedUser['ID'], $TorrentID, $Type, $userComment, $Tracks, $Images, $ExtraIDs, $Links
);

$Cache->delete_value("reports_torrent_$TorrentID");
$Cache->increment('num_torrent_reportsv2');

if ($UserID != $LoggedUser['ID']) {
    (new Gazelle\Manager\User)->sendPM($UserID, 0,
        "One of your torrents has been reported",
        $Twig->render('reportsv2/new.twig', [
            'id'     => $TorrentID,
            'title'  => $ReportType['title'],
            'reason' => $userComment,
        ])
    );
}

header("Location: torrents.php?torrentid=$TorrentID");
