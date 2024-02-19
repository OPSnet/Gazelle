<?php

/*
 * This is the AJAX backend for the SendNow() function.
 */

authorize();

if (!$Viewer->permitted('admin_reports')) {
    die();
}

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    json_error("bad parameters");
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

$reportType = (new Gazelle\Manager\Torrent\ReportType())->findByType($_POST['resolve_type'] ?? '');

switch ($_POST['pm_type']) {
    case 'Uploader':
        $ToID = (int)$_POST['uploaderid'];
        if ($Report) {
            $Message = "You uploaded [url=torrents.php?torrentid={$torrent->id()}]the above torrent[/url]. It has been reported for the reason: "
                . $reportType->name() . "\n\n$Message";
        } else {
            $Message = "I am PMing you as you are the uploader of [url=torrents.php?torrentid={$torrent->id()}]the above torrent[/url].\n\n$Message";
        }
        break;
    case 'Reporter':
        $ToID = (int)$_POST['reporterid'];
        $Message = "You reported [url=torrents.php?torrentid={$torrent->id()}]the above torrent[/url] for the reason "
            . $reportType->name() . ":\n[quote]" . $_POST['report_reason'] . "[/quote]\n$Message";
        break;
    default:
        json_error("no recipient target");
}

$recipient = (new Gazelle\Manager\User())->findById($ToID);
if (is_null($recipient)) {
    json_error("bad recipient id");
} elseif ($ToID == $Viewer->id()) {
    json_error("message to self");
}

$pm = $recipient->inbox()->create($Viewer, $_POST['raw_name'] ?? "Report", $Message);

echo "PM delivered, msg id {$pm->id()}";
