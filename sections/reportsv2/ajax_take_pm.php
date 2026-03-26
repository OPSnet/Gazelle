<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

/*
 * This is the AJAX backend for the SendPM() function.
 */

if (!$Viewer->permitted('admin_reports')) {
    Error403::error();
}

header('Content-Type: application/json');

authorize(true);

$torrent = new Manager\Torrent()->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    json_error("no such torrent");
}

if (isset($_POST['uploader_pm']) && $_POST['uploader_pm'] != '') {
    $Message = $_POST['uploader_pm'];
} else {
    json_error("no message");
}

$Report = false;
if (!isset($_POST['from_delete'])) {
    $Report = true;
} elseif (!is_number($_POST['from_delete'])) {
    json_error("bad parameters from_delete");
}

$reportType = new Manager\Torrent\ReportType()->findByType($_POST['resolve_type'] ?? '');

switch ($_POST['pm_type']) {
    case 'Uploader':
        $ToID = (int)$_POST['uploaderid'];
        if ($Report) {
            $Message = "You uploaded [url={$torrent->location()}]the above torrent[/url]. It has been reported for the reason: "
                . $reportType->name() . "\n\n$Message";
        } else {
            $Message = "I am PMing you as you are the uploader of [url={$torrent->location()}]the above torrent[/url].\n\n$Message";
        }
        break;
    case 'Reporter':
        $report = new Manager\Torrent\Report()->findById((int)$_POST['reportid']);
        if (!$report) {
            json_error("no such report");
        }
        $ToID = $report->reporterId();
        $Message = "You reported [url={$torrent->location()}]the above torrent[/url] for the reason "
            . $reportType->name() . ":\n[quote]" . $report->reason() . "[/quote]\n\n$Message";
        break;
    default:
        json_error("no recipient target");
}

$recipient = new Manager\User()->findById($ToID);
if (is_null($recipient)) {
    json_error("bad recipient id");
} elseif ($ToID === $Viewer->id()) {
    json_error("message to self");
}

$pm = $recipient->inbox()->create($Viewer, "Reported torrent {$torrent->id} - {$torrent->fullName()}", $Message);

echo json_encode([
    'status'  => 'success',
    'conv_id' => $pm->id,
    'message' => "PM sent " . $pm->publicLocation(),
]);
