<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\TorrentFlag;

/*
 * This is the backend of the AJAXy reports resolve (When you press the shiny submit button).
 * This page shouldn't output anything except in error. If you do want output, it will be put
 * straight into the table where the report used to be. Currently output is only given when
 * a collision occurs or a POST attack is detected.
 */

header('Content-type: application/json');

if (!$Viewer->permitted('admin_reports')) {
    json_die("failure", "forbidden");
}

authorize(true);

$fromReportPage = !isset($_POST['from_delete']);
$reportTypeMan  = new Manager\Torrent\ReportType();
$torMan         = new Manager\Torrent();
$reportMan      = new Manager\Torrent\Report($torMan);
$userMan        = new Manager\User();
$isDelete       = isset($_POST['delete']);

$report = $reportMan->findById((int)($_POST['reportid'] ?? 0));
if (is_null($report)) {
    // torrent is being deleted using RM link
    $torrent = $torMan->findById((int)($_POST['torrentid'] ?? 0));
    if (is_null($torrent)) {
        json_die("failure", "torrent not found");
    }
    $reportType = $reportTypeMan->findByType($_POST['resolve_type'] ?? '');
    if (is_null($reportType)) {
        json_die("failure", "bad report type");
    }
    $report = $reportMan->create(
        torrent:     $torrent,
        user:        $Viewer,
        reportType:  $reportType,
        irc:         new Util\Irc(),
        reason:      '',
        otherIdList: '',
    );
} else {
    $torrent = $report->torrent();
}

if (is_null($torrent)) {
    $report->moderatorResolve($Viewer, 'Report already dealt with (torrent deleted).');
    json_die("failure", "torrent already deleted?");
}
if ($isDelete && $torrent->hasUploadLock()) {
    json_die("failure", "You requested to delete the torrent {$torrent->id}, but this is currently not possible because the upload process is still running. Please try again later.");
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
    if ($report->moderatorResolve($Viewer, $comment) < 1) {
        echo $Twig->render('reportsv2/already-resolved.twig', ['report' => $report]);
    }
    exit;
}

if ($fromReportPage && !$report->moderatorResolve($Viewer, $_POST['comment'] ?? '')) {
    echo $Twig->render('reportsv2/already-resolved.twig', ['report' => $report]);
    exit;
}

if ($_POST['resolve_type'] == $report->type()) {
    $reportTypeName = $report->reportType()->name();
} else {
    $reportType = $reportTypeMan->findByType($_POST['resolve_type'] ?? '');
    if (is_null($reportType)) {
        json_die("failure", "invalid report type");
    }
    $reportTypeName = $reportType->name();
}

$sendPM = false;
$flagAdded = null;
if ($_POST['resolve_type'] === 'tags_lots') {
    $flagAdded = TorrentFlag::badTag;
    $sendPM = true;
} elseif ($_POST['resolve_type'] === 'folders_bad') {
    $flagAdded = TorrentFlag::badFolder;
    $sendPM = true;
} elseif ($_POST['resolve_type'] === 'filename') {
    $flagAdded = TorrentFlag::badFile;
    $sendPM = true;
} elseif ($_POST['resolve_type'] === 'lineage') {
    $flagAdded = TorrentFlag::noLineage;
} elseif ($_POST['resolve_type'] === 'lossyapproval') {
    $flagAdded = TorrentFlag::lossyMaster;
}
if ($flagAdded) {
    $report->addTorrentFlag($flagAdded, $Viewer);
}

$adminMessage  = trim($_POST['admin_message']);
$logMessage    = isset($_POST['log_message']) ? trim($_POST['log_message']) : null;
$name          = $torrent->fullName() . ' (' . byte_format($torrent->size()) . ')';
$path          = $torrent->path();
$uploader      = $torrent->uploader();
$pmUploader    = $sendPM || $weeksWarned > 0 || $isDelete;
$revokeUpload  = isset($_POST['upload']);
$replacementId = null;

//Log and delete
if (!$isDelete) {
    $torrent->flush();
    $Log = $logMessage ?? "No log message (torrent wasn't deleted).";
} elseif ($torrent->isDeleted()) {
    $Log = $logMessage ?? "No log message (torrent was already deleted).";
} else {
    [$ok, $message] = $torrent->removeTorrent( /** @phpstan-ignore-line TorrentDeleted vs Torrent confusion */
        $Viewer,
        sprintf('%s (%s)', $reportTypeName, $logMessage ?? 'none'),
        $report->reportType()->trackerReason()
    );
    if (!$ok) {
        echo "Failure: $message<br />";
        exit;
    }

    $torrent = new TorrentDeleted($torrent->id);
    $logList = ["Torrent {$torrent->id} ($name) uploaded by {$uploader->username()} was deleted by {$Viewer->username()}"];
    if ($_POST['resolve_type'] != 'custom') {
        $logList[] = "for the reason: {$reportTypeName}.";
    }
    if ($logMessage) {
        $logList[] = ", $logMessage";
    }
    $Log = implode(' ', $logList);

    if (
        in_array($_POST['resolve_type'], ['checksum_trump', 'trump'])
        && preg_match('/torrentid=([0-9]+)/', $logMessage, $match) === 1
    ) {
        $replacementId = (int)$match[1];
    }
    $userMan->sendRemovalPm($uploader, $torrent->id, $name, $path, $Log, $replacementId, false);
}

// send PMs to those concerned
if ($modNote || $pmUploader) {
    $newTorrent = $replacementId ? $torMan->findById($replacementId) : null;
    $pmTitle = "report handled";
    $firstMessage = "[url={$torrent->location()}]Your above torrent[/url] was reported ";

    if ($isDelete && $newTorrent) {
        $pmTitle = "replaced";
        $firstMessage .= 'and has been replaced by ' . $newTorrent->publicLocation();
    } elseif ($isDelete) {
        $pmTitle = "removed";
        $firstMessage .= 'and has been deleted.';
    } elseif ($flagAdded) {
        $pmTitle = "marked";
        $firstMessage .= "and has been marked as [b]{$flagAdded->label()}[/b]";
    } else {
        $firstMessage .= 'but not deleted.';
    }

    $message = [$firstMessage];
    $reportReason = $report->reportType()->pmBody();
    if ($reportReason) {
         $message[] = "Reason: $reportReason";
    }
    if ($weeksWarned > 0) {
        $message[] = "This has resulted in a [url=wiki.php?action=article&name=warnings]{$weeksWarned} week warning.[/url]";
    }
    if ($revokeUpload) {
        $message[] = 'This has ' . ($weeksWarned > 0 ? 'also ' : '') . "resulted in the loss of your upload privileges.";
    }
    if ($Log && $isDelete) {
        $message[] = "[url={$torrent->location()}]Log message[/url]:[quote]{$Log}[/quote]";
    }
    if ($modNote) {
        $message[] = "[quote={$Viewer->username()}]{$modNote}[/quote]";
    }
    $message[] = "The report was handled by [user]{$Viewer->username()}[/user].";
    $uploader->inbox()->createSystem("Uploaded torrent {$pmTitle}: $name", implode("\n\n", $message));
}

if ($weeksWarned > 0) {
    $staffNote = $message = "Uploader of torrent ({$torrent->id}) $name which was resolved with the preset: $reportTypeName.";
    if ($adminMessage) {
        $staffNote .= " ($adminMessage)";
    }
    if ($revokeUpload) {
        $staffNote .= ' (Upload privileges removed).';
        $message   .= ' (Upload privileges removed).';
    }
    $uploader->warn($weeksWarned, $staffNote, $Viewer, $message);
} else {
    $staffNote = [];
    if ($revokeUpload) {
        $staffNote[] = "Upload privileges removed by {$Viewer->username()}\nReason: Uploader of torrent ($torrent->id) $name which was [url={$report->location()}]resolved with the preset: {$reportTypeName}[/url].";
    }
    if ($adminMessage) {
        // They did nothing of note, but still want to mark it (Or upload and mark)
        $staffNote[] = $revokeUpload
            ? "mod note: $adminMessage"
            : "torrent ($torrent->id) $name [url={$report->location()}]was reported[/url]: $adminMessage";
    }
    if ($staffNote) {
        $uploader->addStaffNote(implode(', ', $staffNote));
    }
}

if ($revokeUpload) {
    $uploader->toggleAttr('disable-upload', true);
}
$uploader->modify(); // if there are notes to add

// Now we've done everything, update the DB with values
if ($fromReportPage) {
    $report->finalize($Log, $_POST['comment']);
}

echo '{"status":"success"}';
