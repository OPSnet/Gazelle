<?php
/*
 * This page handles the backend from when a user submits a report.
 * It checks for (in order):
 * 1. The usual POST injections, then checks that things.
 * 2. Things that are required by the report type are filled
 * 3. Things that are filled are filled with correct things.
 * 4. That the torrent you're reporting still exists.
 *
 * Then it just inserts the report to the DB and increments the counter.
 */

authorize();

$torMan = new Gazelle\Manager\Torrent;
$torrent = $torMan->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

$reportMan = new Gazelle\Manager\Torrent\Report($torMan);
if ($reportMan->existsRecent($torrent->id(), $Viewer->id())) {
    error("Slow down, you're moving too fast!");
}

$reportType = (new Gazelle\Manager\Torrent\ReportType)->findByType($_POST['type'] ?? '');
if (is_null($reportType)) {
    error("bad report type");
}

if ($reportType->needImage() === 'required') {
    $field = 'image';
    if (empty($_POST[$field])) {
        error("You are missing a required field ($field) for a {$reportType->name()} report.");
    }
}

$ExtraIDs = '';
if ($reportType->needSitelink() !== 'none') {
    $sitelink = trim($_POST['sitelink'] ?? '');
    if ($sitelink === '') {
        if ($reportType->needSitelink() === 'required') {
            error("You must supply a permalink [PL] in your report");
        }
    } else {
        if (!preg_match_all(TORRENT_REGEXP, $sitelink, $match)) {
            error("The permalink was incorrect. Please copy the torrent permalink URL, which is labelled as [PL] and is found next to the [DL] buttons.");
        }
        $all = $match['id'];
        if (in_array($torrent->id(), $all)) {
            error("The extra permalinks you gave included the link to the torrent you're reporting!");
        }
        $ExtraIDs = implode(' ', $all);
    }
}

$Links = '';
if ($reportType->needLink() !== 'none') {
    $link = trim($_POST['link'] ?? '');
    if ($link === '' && $reportType->needLink() === 'required') {
        error("You must supply one or more links in your report");
    } elseif ($link != '') {
        if (!preg_match_all(URL_REGEXP, $link, $match)) {
            error("The extra links you provided weren't links...");
        }
        $Links = implode(' ', $match[1]);
    }
}

$Images = '';
if ($reportType->needImage() !== 'none') {
    $image = trim($_POST['image'] ?? '');
    if ($image === '') {
        if ($reportType->needImage() === 'required') {
            error("You must supply one or more images in your report");
        }
    } else {
        if (!preg_match_all(IMAGE_REGEXP, $image, $match)) {
            error("The extra image links you provided weren't links to images...");
        }
        $Images = implode(' ', $match[1]);
    }
}

$trackList = '';
if ($reportType->needTrack() !== 'none') {
    $trackList = trim($_POST['track']);
    if ($trackList !== 'all') {
        $split = preg_split('/\D+/', $trackList);
        $trackList = ($split === false)
            ? ''
            : implode(' ', array_filter(array_map('intval', $split), fn ($n) => $n));
        if ($reportType->needTrack() === 'required' && $trackList === '') {
            error('Tracks should be given in a space-separated list of numbers with no other characters, or "all".');
        }
    }
}

$reason = trim($_POST['extra']);
if (empty($reason)) {
    error("As useful as blank reports are, could you be a tiny bit more helpful? (Leave a comment)");
}

$report = $reportMan->create(
    torrent:     $torrent,
    user:        $Viewer,
    reportType:  $reportType,
    reason:      $reason,
    otherIdList: $ExtraIDs,
    track:       $trackList,
    image:       $Images,
    link:        $Links,
);

if (!$reportType->isInvisible() && $torrent->uploaderId() != $Viewer->id()) {
    (new Gazelle\Manager\User)->sendPM($torrent->uploaderId(), 0,
        "One of your torrents has been reported",
        $Twig->render('reportsv2/new.twig', [
            'id'     => $torrent->id(),
            'title'  => $reportType->name(),
            'reason' => $report->reason(),
        ])
    );
}

header('Location: ' . $torrent->location());
