<?php
/*
 * This is the backend of the AJAXy reports resolve (When you press the shiny submit button).
 * This page shouldn't output anything except in error. If you do want output, it will be put
 * straight into the table where the report used to be. Currently output is only given when
 * a collision occurs or a POST attack is detected.
 */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}
authorize();

$fromReportPage = !isset($_POST['from_delete']);

$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();

$report = $reportMan->findById((int)($_POST['reportid'] ?? 0));
if (is_null($report)) {
    error("error report id");
}

$modNote = trim($_POST['uploader_pm']);
if ($_POST['pm_type'] != 'Uploader') {
    $modNote = '';
}

$weeksWarned = (int)$_POST['warning'];
if (!in_array($weeksWarned, range(0, 8))) {
    error("error weeks warning");
}

$reportTypeId = (int)$_POST['categoryid'];
if (!$reportTypeId) {
    error("error report type id");
}

if (!isset($_POST['resolve_type'])) {
    error("No Resolve Type");
} elseif (array_key_exists($_POST['resolve_type'], $Types[$reportTypeId])) {
    $ResolveType = $Types[$reportTypeId][$_POST['resolve_type']];
} elseif (array_key_exists($_POST['resolve_type'], $Types['master'])) {
    $ResolveType = $Types['master'][$_POST['resolve_type']];
} elseif (!in_array($_POST['resolve_type'], ['manual', 'dismiss'])) {
    //There was a type but it wasn't an option!
    error("Invalid Resolve Type");
}

$torrentId = (int)($_POST['torrentid'] ?? 0);
if (!$torrentId) {
    error("error torrent id");
}
$report->setTorrentId($torrentId);
if (isset($_POST['delete']) && $Cache->get_value("torrent_$torrentId".'_lock')) {
    error("You requested to delete the torrent $torrentId, but this is currently not possible because the upload process is still running. Please try again later.");
}

if ($fromReportPage && in_array($_POST['resolve_type'], ['manual', 'dismiss'])) {
    if ($_POST['comment']) {
        $comment = $_POST['comment'];
    } else {
        if ($_POST['resolve_type'] === 'manual') {
            $comment = 'Report was resolved manually.';
        } elseif ($_POST['resolve_type'] === 'dismiss') {
            $comment = 'Report was dismissed as invalid.';
        }
    }
    if ($report->moderatorResolve($Viewer->id(), $comment)) {
        $Cache->deleteMulti(['num_torrent_reportsv2', "reports_torrent_$torrentId"]);
    } else {
        echo $Twig->render('reportsv2/already-resolved.twig', ['report' => $report]);
    }
    exit;
}

$torrent = (new Gazelle\Manager\Torrent)->findById($torrentId);
if (is_null($torrent)) {
    $report->moderatorResolve($Viewer->id(), 'Report already dealt with (torrent deleted).');
    $Cache->decrement('num_torrent_reportsv2');
}

if ($fromReportPage && !$report->moderatorResolve($Viewer->id(), $_POST['comment'] ?? '')) {
    echo $Twig->render('reportsv2/already-resolved.twig', ['report' => $report]);
    exit;
}

$report->setModeratorId($Viewer->id())->setGroupId($torrent->groupId())->setTorrentId($torrentId);

if ($_POST['resolve_type'] === 'tags_lots') {
    $report->setTorrentFlag('torrents_bad_tags');
    $SendPM = true;
}
elseif ($_POST['resolve_type'] === 'folders_bad') {
    $report->setTorrentFlag('torrents_bad_folders');
    $SendPM = true;
}
elseif ($_POST['resolve_type'] === 'filename') {
    $report->setTorrentFlag('torrents_bad_files');
    $SendPM = true;
}
elseif ($_POST['resolve_type'] === 'lineage') {
    $report->setTorrentFlag('torrents_missing_lineage');
}
elseif ($_POST['resolve_type'] === 'lossyapproval') {
    $report->setTorrentFlag('torrents_lossymaster_approved');
}

$adminMessage = trim($_POST['admin_message']);
$logMessage = isset($_POST['log_message']) ? trim($_POST['log_message']) : null;
$uploader = $torrent->uploader();
$name = $torrent->fullName() . ' (' . Format::get_size($torrent->size()) . ')';

//Log and delete
if (!(isset($_POST['delete']) && $Viewer->permitted('users_mod'))) {
    $Log = $logMessage ?? "No log message (torrent wasn't deleted).";
} else {
    [$ok, $message] = $torrent->remove(
        $Viewer->id(),
        sprintf('%s (%s)', $ResolveType['title'], $logMessage ?? 'none'),
        $ResolveType['reason']
    );
    if (!$ok) {
        echo "Failure: $message<br />";
        exit;
    }

    $Log = "Torrent $torrentId ($name) uploaded by " . $uploader->username()
        . " was deleted by " . $Viewer->username()
        . ($_POST['resolve_type'] == 'custom' ? '' : ' for the reason: ' . $ResolveType['title'] . ".")
        . ($logMessage ? " $logMessage" : '');

    $TrumpID = 0;
    if ($_POST['resolve_type'] === 'trump' && preg_match('/torrentid=([0-9]+)/', $logMessage, $match) === 1) {
        $TrumpID = $match[1];
    }
    $pmUploader = $weeksWarned > 0 || isset($_POST['delete']) || $SendPM;
    $userMan->sendRemovalPM($torrentId, $uploader->id(), $name, $Log, $TrumpID, $pmUploader);
}

$revokeUpload = isset($_POST['upload']);
if ($revokeUpload) {
    $uploader->revokeUpload();
}

if ($weeksWarned > 0) {
    $WarnLength = $weeksWarned * (7 * 86400);
    $Reason = "Uploader of torrent ($torrentId) $name which was resolved with the preset: {$ResolveType['title']}.";
    if ($adminMessage) {
        $Reason .= " ($adminMessage)";
    }
    if ($revokeUpload) {
        $Reason .= ' (Upload privileges removed).';
    }
    $userMan->warn($uploader->id(), $WarnLength, $Reason, $Viewer->username());
} else {
    $staffNote = null;
    if ($revokeUpload) {
        $staffNote = 'Upload privileges removed by '.$Viewer->username()
            . "\nReason: Uploader of torrent ($torrentId) $name which was [url=". $report->url() . "]resolved with the preset: "
            . $ResolveType['title'] . "[/url].";
    }
    if ($adminMessage) {
        // They did nothing of note, but still want to mark it (Or upload and mark)
        if (!$revokeUpload) {
            $staffNote = "Torrent ($torrentId) $name [url=". $report->url() . "]was reported[/url]: $adminMessage";
        } else {
            $staffNote .= ", mod note: $adminMessage";
        }
    }
    if ($staffNote) {
        $uploader->addStaffNote($staffNote)->modify();
    }
}

//PM
if ($modNote || $weeksWarned > 0 || isset($_POST['delete']) || $SendPM) {
    $message = [
        "[url=torrents.php?torrentid=$torrentId]Your above torrent[/url] was reported "
        . (isset($_POST['delete']) ? 'and has been deleted.' : 'but not deleted.')
    ];

    $Preset = $ResolveType['resolve_options']['pm'];
    if ($Preset != '') {
         $message[] = "Reason: $Preset";
    }

    if ($weeksWarned > 0) {
        $message[] = "This has resulted in a [url=wiki.php?action=article&amp;name=warnings]$weeksWarned week warning.[/url]";
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
    $report->finalize($_POST['resolve_type'], $Log, $_POST['comment']);
}
