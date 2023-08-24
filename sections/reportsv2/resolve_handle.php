<?php

use Gazelle\Enum\TorrentFlag;

/*
 * This is the backend of the AJAXy reports resolve (When you press the shiny submit button).
 * This page shouldn't output anything except in error. If you do want output, it will be put
 * straight into the table where the report used to be. Currently output is only given when
 * a collision occurs or a POST attack is detected.
 */

if (!$Viewer->permitted('admin_reports')) {
    json_die("failure", "forbidden");
}

authorize();

$fromReportPage = !isset($_POST['from_delete']);
$reportMan      = new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent);
$userMan        = new Gazelle\Manager\User;

$report = $reportMan->findById((int)($_POST['reportid'] ?? 0));
if (is_null($report)) {
    json_die("failure", "error report id");
}

$torrent = $report->torrent();
if (is_null($torrent)) {
    $report->moderatorResolve($Viewer->id(), 'Report already dealt with (torrent deleted).');
    $Cache->decrement_value('num_torrent_reportsv2');
    json_die("failure", "torrent already deleted?");
}
$torrentId = $torrent->id();
if (isset($_POST['delete']) && $torrent->hasUploadLock()) {
    json_die("You requested to delete the torrent $torrentId, but this is currently not possible because the upload process is still running. Please try again later.");
}

$modNote = trim($_POST['uploader_pm']);
if ($_POST['pm_type'] != 'Uploader') {
    $modNote = '';
}

$weeksWarned = (int)$_POST['warning'];
if (!in_array($weeksWarned, range(0, 8))) {
    json_die("failure", "error weeks warning");
}

if ($fromReportPage && in_array($_POST['resolve_type'], ['manual', 'dismiss'])) {
    if ($_POST['comment']) {
        $comment = $_POST['comment'];
    } elseif ($_POST['resolve_type'] === 'manual') {
        $comment = 'Report was resolved manually.';
    } else {
        $comment = 'Report was dismissed as invalid.';
    }
    if ($report->moderatorResolve($Viewer->id(), $comment)) {
        $Cache->delete_multi(['num_torrent_reportsv2', "reports_torrent_$torrentId"]);
    } else {
        echo $Twig->render('reportsv2/already-resolved.twig', ['report' => $report]);
    }
    exit;
}

if ($fromReportPage && !$report->moderatorResolve($Viewer->id(), $_POST['comment'] ?? '')) {
    echo $Twig->render('reportsv2/already-resolved.twig', ['report' => $report]);
    exit;
}

$SendPM = false;
if ($_POST['resolve_type'] === 'tags_lots') {
    $report->addTorrentFlag(TorrentFlag::badTag, $Viewer);
    $SendPM = true;
} elseif ($_POST['resolve_type'] === 'folders_bad') {
    $report->addTorrentFlag(TorrentFlag::badFolder, $Viewer);
    $SendPM = true;
} elseif ($_POST['resolve_type'] === 'filename') {
    $report->addTorrentFlag(TorrentFlag::badFile, $Viewer);
    $SendPM = true;
} elseif ($_POST['resolve_type'] === 'lineage') {
    $report->addTorrentFlag(TorrentFlag::noLineage, $Viewer);
} elseif ($_POST['resolve_type'] === 'lossyapproval') {
    $report->addTorrentFlag(TorrentFlag::lossyMaster, $Viewer);
}

$adminMessage   = trim($_POST['admin_message']);
$logMessage     = isset($_POST['log_message']) ? trim($_POST['log_message']) : null;
$name           = $torrent->fullName() . ' (' . byte_format($torrent->size()) . ')';
$reportTypeName = $report->reportType()->name();
$uploader       = $torrent->uploader();

//Log and delete
if (!(isset($_POST['delete']) && $Viewer->permitted('users_mod'))) {
    $torrent->flush();
    $Log = $logMessage ?? "No log message (torrent wasn't deleted).";
} elseif ($torrent->isDeleted()) {
    $Log = $logMessage ?? "No log message (torrent was already deleted).";
} else {
    [$ok, $message] = $torrent->remove( /** @phpstan-ignore-line */
        $Viewer,
        sprintf('%s (%s)', $reportTypeName, $logMessage ?? 'none'),
        $report->reportType()->trackerReason()
    );
    if (!$ok) {
        echo "Failure: $message<br />";
        exit;
    }

    $Log = "Torrent $torrentId ($name) uploaded by " . $uploader->username()
        . " was deleted by " . $Viewer->username()
        . ($_POST['resolve_type'] == 'custom' ? '' : " for the reason: {$reportTypeName}.")
        . ($logMessage ? " $logMessage" : '');

    $TrumpID = 0;
    if ($_POST['resolve_type'] === 'trump' && preg_match('/torrentid=([0-9]+)/', $logMessage, $match) === 1) {
        $TrumpID = (int)$match[1];
    }
    $pmUploader = $weeksWarned > 0 || isset($_POST['delete']) || $SendPM;
    $userMan->sendRemovalPm($torrentId, $uploader->id(), $name, $Log, $TrumpID, $pmUploader);
}

$revokeUpload = isset($_POST['upload']);
if ($revokeUpload) {
    $uploader->toggleAttr('disable-upload', true);
}

if ($weeksWarned > 0) {
    $Reason = $message = "Uploader of torrent ($torrentId) $name which was resolved with the preset: $reportTypeName.";
    if ($adminMessage) {
        $Reason .= " ($adminMessage)";
    }
    if ($revokeUpload) {
        $Reason  .= ' (Upload privileges removed).';
        $message .= ' (Upload privileges removed).';
    }
    $uploader->warn($weeksWarned, $Reason, $Viewer, $message);
} else {
    $staffNote = null;
    if ($revokeUpload) {
        $staffNote = 'Upload privileges removed by ' . $Viewer->username()
            . "\nReason: Uploader of torrent ($torrentId) $name which was [url="
            . $report->location() . "]resolved with the preset: {$reportTypeName}[/url].";
    }
    if ($adminMessage) {
        // They did nothing of note, but still want to mark it (Or upload and mark)
        if (!$revokeUpload) {
            $staffNote = "Torrent ($torrentId) $name [url=" . $report->location() . "]was reported[/url]: $adminMessage";
        } else {
            $staffNote .= ", mod note: $adminMessage";
        }
    }
    if ($staffNote) {
        $uploader->addStaffNote($staffNote);
    }
}
$uploader->modify(); // if there are notes to add

//PM
if ($modNote || $weeksWarned > 0 || isset($_POST['delete']) || $SendPM) {
    $message = [
        "[url=torrents.php?torrentid=$torrentId]Your above torrent[/url] was reported "
        . (isset($_POST['delete']) ? 'and has been deleted.' : 'but not deleted.')
    ];

    $Preset = $report->reportType()->pmBody();
    if ($Preset != '') {
         $message[] = "Reason: $Preset";
    }

    if ($weeksWarned > 0) {
        $message[] = "This has resulted in a [url=wiki.php?action=article&name=warnings]$weeksWarned week warning.[/url]";
    }

    if ($revokeUpload) {
        $message[] = 'This has ' . ($weeksWarned > 0 ? 'also ' : '') . "resulted in the loss of your upload privileges.";
    }

    if ($Log) {
        $message[] = "Log Message: $Log";
    }

    if ($modNote) {
        $message[] = "Message from " . $Viewer->username() . ": $modNote";
    }

    $message[] = "Report was handled by [user]" . $Viewer->username() . "[/user].";
    $userMan->sendPM($uploader->id(), 0, $name, implode("\n\n", $message));
}

$Cache->delete_value("reports_torrent_$torrentId");

// Now we've done everything, update the DB with values
if ($fromReportPage) {
    $Cache->decrement('num_torrent_reportsv2');
    $report->finalize($Log, $_POST['comment']);
}
