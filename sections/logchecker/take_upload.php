<?php

use OrpheusNET\Logchecker\Logchecker;

ini_set('upload_max_filesize', 1000000);

if (empty($_POST['torrentid'])) {
    error('No torrent is selected.');
}
$TorrentID = intval($_POST['torrentid']) ?? null;
// Some browsers will report an empty file when you submit, prune those out
$_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });
$FileCount = count($_FILES['logfiles']['name']);
if ($FileCount == 0) {
    error("No logfiles uploaded.\n");
}
$Action = in_array($_POST['from_action'], ['upload', 'update']) ? $_POST['from_action'] : 'upload';

$sql = "SELECT t.ID, t.GroupID
    FROM torrents t
    WHERE t.HasLog = '1' AND t.ID = ?
";
$args = [$TorrentID];

if (!check_perms('users_mod')) {
    $sql .= " AND t.UserID = ?";
    $args[] = $Viewer->id();
}
list($TorrentID, $GroupID) = $DB->row($sql, ...$args);
if (!$TorrentID) {
    error('Invalid torrent id.');
}

$ripFiler = new Gazelle\File\RipLog;
$ripFiler->remove([$TorrentID, null]);

$htmlFiler = new Gazelle\File\RipLogHTML;
$htmlFiler->remove([$TorrentID, null]);

$DB->prepared_query('
    DELETE FROM torrents_logs WHERE TorrentID = ?
    ', $TorrentID
);

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

    $DB->prepared_query('
        INSERT INTO torrents_logs
               (TorrentID, Score, `Checksum`, `FileName`, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Details)
        VALUES (?,         ?,      ?,          ?,         ?,      ?,              ?,         ?,             ?,                 ?)
        ', $TorrentID, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(), $logfile->ripper(),
            $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
            Logchecker::getLogcheckerVersion(), $logfile->detailsAsString()
    );
    $LogID = $DB->inserted_id();

    $ripFiler->put($logfile->filepath(), [$TorrentID, $LogID]);
    $htmlFiler->put($logfile->text(), [$TorrentID, $LogID]);
}

$DB->prepared_query("
    UPDATE torrents SET
        HasLogDB = '1',
        LogScore = ?,
        LogChecksum = ?
    WHERE ID = ?
    ", $logfileSummary->overallScore(), $logfileSummary->checksumStatus(),
        $TorrentID
);
$Cache->deleteMulti(["torrent_group_{$GroupID}", "torrents_details_{$GroupID}", "tg_{$GroupID}", "tlist_{$GroupID}"]);

View::show_header('Logchecker results');
?>
<div class="thin center">
    <br /><a href="logchecker.php?action=<?= $Action ?>">Upload another log file</a>
</div>
<?php foreach ($logfileSummary->all() as $logfile) { ?>
<div class="thin">
    <?= $Twig->render('logchecker/report.twig', ['logfile' => $logfile]) ?>
</div>
<?php
}

View::show_footer();
