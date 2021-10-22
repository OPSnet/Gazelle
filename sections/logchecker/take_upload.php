<?php

use OrpheusNET\Logchecker\Logchecker;

ini_set('upload_max_filesize', 1000000);

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    error('No torrent is selected.');
}
if ($torrent->media() !== 'CD') {
    error('Media of torrent precludes adding a log.');
}
if ($torrent->uploaderId() != $Viewer->id() && !$Viewer->permitted('admin_add_log')) {
    error('Not your upload.');
}

// Some browsers will report an empty file when you submit, prune those out
$_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });
if (count($_FILES['logfiles']['name']) == 0) {
    error("No logfiles uploaded.\n");
}
$action = in_array($_POST['from_action'], ['upload', 'update']) ? $_POST['from_action'] : 'upload';

$ripFiler = new Gazelle\File\RipLog;
$ripFiler->remove([$torrent->id(), null]);

$htmlFiler = new Gazelle\File\RipLogHTML;
$htmlFiler->remove([$torrent->id(), null]);

$torrent->removeLogDb();

$logfileSummary = new Gazelle\LogfileSummary;
foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
    if (!$_FILES['logfiles']['size'][$Pos]) {
        break;
    }
    $logfile = new Gazelle\Logfile(
        $_FILES['logfiles']['tmp_name'][$Pos],
        $_FILES['logfiles']['name'][$Pos]
    );
    $logfileSummary->add($logfile);
    $logId = $torrent->addLogDb($logfile, Logchecker::getLogcheckerVersion());

    $ripFiler->put($logfile->filepath(), [$torrent->id(), $logId]);
    $htmlFiler->put($logfile->text(), [$torrent->id(), $logId]);
}
$torrent->updateLogScore($logfileSummary);

echo $Twig->render('logchecker/result.twig', [
    'summary' => $logfileSummary->all(),
    'action'  => $action,
]);
