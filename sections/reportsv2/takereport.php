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

$CategoryID = (int)$_POST['categoryid'];
if (!$CategoryID) {
    error("report category not set");
}

$torMan = new Gazelle\Manager\Torrent;
$torrent = $torMan->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

$reportMan = new Gazelle\Manager\Torrent\Report($torMan);
$Type = $_POST['type'];
$ReportType = $reportMan->type($Type);

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
    $torMan = new Gazelle\Manager\Torrent;
    if (!preg_match_all(TORRENT_REGEXP, $_POST['sitelink'], $match)) {
        $Err = 'The permalink was incorrect. Please copy the torrent permalink URL, which is labelled as [PL] and is found next to the [DL] buttons.';
    } else {
        $all = $match['id'];
        if (in_array($torrent->id(), $all)) {
            $Err = "The extra permalinks you gave included the link to the torrent you're reporting!";
        }
        $ExtraIDs = implode(' ', $all);
    }
}

if (empty($_POST['link'])) {
    $Links = '';
} else {
    if (preg_match_all(URL_REGEXP, $_POST['link'], $match)) {
        $Links = implode(' ', $match[1]);
    } else {
        $Err = "The extra links you provided weren't links...";
    }
}

if (empty($_POST['image'])) {
    $Images = '';
} else {
    if (preg_match_all(IMAGE_REGEXP, trim($_POST['image']), $match)) {
        $Images = implode(' ', $match[1]);
    } else {
        $Err = "The extra image links you provided weren't links to images...";
    }
}

if (empty($_POST['track'])) {
    $Tracks = '';
} else {
    if (preg_match('/(\d+(?:\s+\d+)*)|all/is', $_POST['track'], $Matches)) {
        $Tracks = $Matches[0];
    } else {
        $Err = 'Tracks should be given in a space-separated list of numbers with no other characters.';
    }
}

$userComment = trim($_POST['extra']);
if (empty($userComment)) {
    $Err = 'As useful as blank reports are, could you be a tiny bit more helpful? (Leave a comment)';
}

if ($reportMan->existsRecent($torrent->id(), $Viewer->id())) {
    $Err = "Slow down, you're moving too fast!";
}

if (!empty($Err)) {
    error($Err);
}

$reportMan->createReport($torrent, $Viewer, $Type, $userComment, $Tracks, $Images, $ExtraIDs, $Links);
if ($torrent->uploaderId() != $Viewer->id()) {
    (new Gazelle\Manager\User)->sendPM($torrent->uploaderId(), 0,
        "One of your torrents has been reported",
        $Twig->render('reportsv2/new.twig', [
            'id'     => $torrent->id(),
            'title'  => $ReportType['title'],
            'reason' => $userComment,
        ])
    );
}

header('Location: ' . $torrent->location());
