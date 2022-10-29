<?php
// perform the back end of updating a resolve type

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}
authorize();

if (empty($_GET['newresolve'])) {
    error("No new resolve");
}

$id = (int)$_GET['reportid'];
if (!$id) {
    error("No report ID");
}

$reportMan = new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent);
$Types = $reportMan->types();
$TypeList = $Types['master'];
$CategoryID = (int)$_GET['categoryid'];
if (!empty($Types[$CategoryID])) {
    $TypeList = array_merge($TypeList, $Types[$CategoryID]);
}

$NewType = $_GET['newresolve'];
if (!array_key_exists($NewType, $TypeList)) {
    error("No resolve from that category");
}

(new Gazelle\ReportV2($id))->changeType($NewType);
