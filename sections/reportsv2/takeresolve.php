<?php
/*
 * This is the backend of the AJAXy reports resolve (When you press the shiny submit button).
 * This page shouldn't output anything except in error. If you do want output, it will be put
 * straight into the table where the report used to be. Currently output is only given when
 * a collision occurs or a POST attack is detected.
 */

if (!check_perms('admin_reports')) {
    error(403);
}
authorize();


//If we're here from the delete torrent page instead of the reports page.
if (!isset($_POST['from_delete'])) {
    $fromReportPage = true;
} else {
    $groupId = (int)$_POST['from_delete'];
    if (!$groupId) {
        error("error from_delete");
    }
    $fromReportPage = false;
}

$modNote = trim($_POST['uploader_pm']);
if ($_POST['pm_type'] != 'Uploader') {
    $_POST['uploader_pm'] = '';
}

$uploaderId = (int)$_POST['uploaderid'];
if (!$uploaderId) {
    error("error uploader id");
}

$weeksWarned = (int)$_POST['warning'];
if (!in_array($weeksWarned, range(0, 8))) {
    error("error weeks warning");
}

$reportTypeId = (int)$_POST['categoryid'];
if (!$reportTypeId) {
    error("error report type id");
}

$reportId = (int)$_POST['reportid'];
if (!$reportId) {
    error("error report id");
}
$reportUrl = "reportsv2.php?view=report&amp;id=$reportId";

$torrentId = (int)$_POST['torrentid'];
if (!$torrentId) {
    error("error torrent id");
}
if (isset($_POST['delete']) && $Cache->get_value("torrent_$torrentId".'_lock')) {
    error("You requested to delete the torrent $torrentId, but this is currently not possible because the upload process is still running. Please try again later.");
}

$report = new Gazelle\ReportV2($reportId);

if ($fromReportPage && in_array($_POST['resolve_type'], ['manual', 'dismiss'])) {
    if ($_POST['comment']) {
        $Comment = $_POST['comment'];
    } else {
        if ($_POST['resolve_type'] === 'manual') {
            $Comment = 'Report was resolved manually.';
        } elseif ($_POST['resolve_type'] === 'dismiss') {
            $Comment = 'Report was dismissed as invalid.';
        }
    }

    if ($report->moderatorResolve($LoggedUser['ID'], $Comment)) {
        $Cache->deleteMulti(['num_torrent_reportsv2', "reports_torrent_$torrentId"]);
    } else {
        //Someone beat us to it. Inform the staffer.
?>
    <table class="layout" cellpadding="5">
        <tr>
            <td>
                <a href="<?= $reportUrl ?>">Somebody has already resolved this report</a>
                <input type="button" value="Clear" onclick="ClearReport(<?= $reportId ?>);" />
            </td>
        </tr>
    </table>
<?php
    }
    exit;
}

$torMan = new Gazelle\Manager\Torrent;
$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();
if (!isset($_POST['resolve_type'])) {
    error("No Resolve Type");
} elseif (array_key_exists($_POST['resolve_type'], $Types[$reportTypeId])) {
    $ResolveType = $Types[$reportTypeId][$_POST['resolve_type']];
} elseif (array_key_exists($_POST['resolve_type'], $Types['master'])) {
    $ResolveType = $Types['master'][$_POST['resolve_type']];
} else {
    //There was a type but it wasn't an option!
    error("Invalid Resolve Type");
}

$GroupID = $DB->scalar("
    SELECT GroupID FROM torrents WHERE ID = ?
    ", $torrentId
);
if (!$GroupID) {
    $report->moderatorResolve($LoggedUser['ID'], 'Report already dealt with (torrent deleted).');
    $Cache->decrement('num_torrent_reportsv2');
}

$check = false;
if ($fromReportPage) {
    $check = $report->moderatorResolve($LoggedUser['ID'], '');
}

//See if it we managed to resolve
if (!($check || !$fromReportPage)) {
    // Someone beat us to it. Inform the staffer.
?>
<a href="<?= $reportUrl ?>">Somebody has already resolved this report</a>
<input type="button" value="Clear" onclick="ClearReport(<?= $reportId ?>);" />
<?php
    exit;
}

$report->setModeratorId($LoggedUser['ID'])->setGroupId($GroupID)->setTorrentId($torrentId);

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

$userMan = new Gazelle\Manager\User;
$uploader = $userMan->findById($uploaderId);
$rawName = trim($_POST['raw_name']);
$adminMessage = trim($_POST['admin_message']);
$logMessage = isset($_POST['log_message']) ? trim($_POST['log_message']) : null;

//Log and delete
if (!(isset($_POST['delete']) && check_perms('users_mod'))) {
    $Log = $logMessage ?? "No log message (torrent wasn't deleted).";
} else {
    $Log = "Torrent $torrentId ($rawName) uploaded by " . $uploader->username()
        . " was deleted by " . $LoggedUser['Username']
        . ($_POST['resolve_type'] == 'custom' ? '' : ' for the reason: ' . $ResolveType['title'] . ".")
        . ($logMessage ? " $logMessage" : '');
    $torMan->findById($torrentId)
        ->remove(
            $LoggedUser['ID'],
            sprintf('%s (%s)', $ResolveType['title'], $logMessage ?? 'none'),
            $ResolveType['reason']
        );

    $TrumpID = 0;
    if ($_POST['resolve_type'] === 'trump' && preg_match('/torrentid=([0-9]+)/', $logMessage, $match) === 1) {
        $TrumpID = $match[1];
    }

    $pmUploader = !$_POST['uploader_pm'] && $weeksWarned <= 0 && !isset($_POST['delete']) && !$SendPM;
    $userMan->sendRemovalPM($torrentId, $uploaderId, $rawName, $Log, $TrumpID, $pmUploader);
}

//Warnings / remove upload
$revokeUpload = isset($_POST['upload']);
if ($revokeUpload) {
    $uploader->revokeUpload();
}

if ($weeksWarned > 0) {
    $WarnLength = $weeksWarned * (7 * 86400);
    $Reason = "Uploader of torrent ($torrentId) $rawName which was resolved with the preset: ".$ResolveType['title'].'.';
    if ($adminMessage) {
        $Reason .= " ($adminMessage)";
    }
    if ($revokeUpload) {
        $Reason .= ' (Upload privileges removed).';
    }
    $userMan->warn($uploaderId, $WarnLength, $Reason, $LoggedUser['Username']);
} else {
    $staffNote = null;
    if ($revokeUpload) {
        $staffNote = 'Upload privileges removed by '.$LoggedUser['Username']
            . "\nReason: Uploader of torrent ($torrentId) $rawName which was [url=$reportUrl]resolved with the preset: "
            . $ResolveType['title'] . "[/url].";
    }
    if ($adminMessage) {
        // They did nothing of note, but still want to mark it (Or upload and mark)
        if (!$revokeUpload) {
            $staffNote = "Torrent ($torrentId) $rawName [url=$reportUrl]was reported[/url]: $adminMessage";
        } else {
            $staffNote .= ", mod note: $adminMessage";
        }
    }
    if ($staffNote) {
        $uploader->addStaffNote($staffNote)->modify();
    }
}

//PM
if ($_POST['uploader_pm'] || $weeksWarned > 0 || isset($_POST['delete']) || $SendPM) {
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

    if ($_POST['uploader_pm']) {
        $message[] = "Message from {$LoggedUser['Username']}: $modNote";
    }

    $message[] = "Report was handled by [user] {$LoggedUser['Username']}[/user].";
    $userMan->sendPM($uploaderId, 0, $rawName, implode("\n\n", $message));
}

$Cache->delete_value("reports_torrent_$torrentId");

// Now we've done everything, update the DB with values
if ($fromReportPage) {
    $Cache->decrement('num_torrent_reportsv2');
    $report->finalize($_POST['resolve_type'], $Log, $_POST['comment']);
}
