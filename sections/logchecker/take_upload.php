<?php

use Gazelle\Logfile;
use Gazelle\LogfileSummary;
use OrpheusNET\Logchecker\Logchecker;

enforce_login();

if (empty($_POST['torrentid'])) {
    error('No torrent is selected.');
}
$TorrentID = intval($_POST['torrentid']) ?? null;
// Some browsers will report an empty file when you submit, prune those out
$_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });
$FileCount = count($_FILES['logfiles']['name']);
$Action = in_array($_POST['from_action'], ['upload', 'update']) ? $_POST['from_action'] : 'upload';

$LogScore = 100;
$LogChecksum = 1;

$Extra = '';
$Params = [$TorrentID, '1'];
if (!check_perms('users_mod')) {
    $Extra = ' AND t.UserID = ?';
    $Params[] = G::$LoggedUser['ID'];
}
$DB->prepared_query("SELECT t.ID, t.GroupID FROM torrents t WHERE t.ID = ? AND t.HasLog=?" . $Extra, ...$Params);

$DetailsArray = [];
$LogfileSummary = new LogfileSummary();
if ($TorrentID != 0 && $DB->has_results() && $FileCount > 0) {
    list($TorrentID, $GroupID) = $DB->next_record(MYSQLI_BOTH);
    $DB->prepared_query("SELECT LogID FROM torrents_logs WHERE TorrentID=?", $TorrentID);
    while(list($LogID) = $DB->next_record(MYSQLI_NUM)) {
        @unlink(SERVER_ROOT_LIVE . "/logs/{$TorrentID}_{$LogID}.log");
    }
    $DB->prepared_query("DELETE FROM torrents_logs WHERE TorrentID=?", $TorrentID);
    ini_set('upload_max_filesize', 1000000);
    foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
        if (!$_FILES['logfiles']['size'][$Pos]) {
            break;
        }
        $Logfile = new Logfile($_FILES['logfiles']['tmp_name'][$Pos], $_FILES['logfiles']['name'][$Pos]);
        $LogfileSummary->add($Logfile);
        $Logs[] = [$Logfile->details(), $Logfile->text()];
        $DB->prepared_query(
            "INSERT INTO torrents_logs
                    (TorrentID, `Log`, Details, Score, `Checksum`, `FileName`, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion)
            VALUES ( ?,         ?,     ?,       ?,     ?,          ?,          ?,      ?,             ?,          ?,             ?)",
            $TorrentID, $Logfile->text(), $Logfile->detailsAsString(), $Logfile->score(), $Logfile->checksumStatus(), $Logfile->filename(), $Logfile->ripper(),
            $Logfile->ripperVersion(), $Logfile->language(), $Logfile->checksumState(), Logchecker::getLogcheckerVersion()
        );
        $LogID = $DB->inserted_id();
        if (move_uploaded_file($Logfile->filepath(), SERVER_ROOT . "/logs/{$TorrentID}_{$LogID}.log") === false) {
            die("Could not copy logfile to the server.");
        }
    }

    $DB->prepared_query(
        "UPDATE torrents SET HasLogDB=?, LogScore=?, LogChecksum=? WHERE ID=?",
        '1', $LogfileSummary->overallScore(), $LogfileSummary->checksumStatus(), $TorrentID
    );
    $Cache->delete_value("torrent_group_{$GroupID}");
    $Cache->delete_value("torrents_details_{$GroupID}");
} else {
    error('No log file uploaded or invalid torrent id was selected.');
}

View::show_header();
echo <<<HTML
<div class="thin center">
    <br><a href="logchecker.php?action={$Action}">Upload another log file</a>
</div>
<div class="thin">
HTML;

if($LogScore == 100) {
    $Color = '#418B00';
}
elseif($LogScore > 90) {
    $Color = '#74C42E';
}
elseif($LogScore > 75) {
    $Color = '#FFAA00';
}
elseif($LogScore > 50) {
    $Color = '#FF5E00';
}
else {
    $Color = '#FF0000';
}

echo "<blockquote><strong>Score:</strong> <span style=\"color:$Color\">$LogScore</span> (out of 100)</blockquote>";

if ($LogChecksum === 0) {
    echo <<<HTML
    <blockquote>
        <strong>Trumpable For:</strong>
        <br /><br />
        Bad/No Checksum(s)
    </blockquote>
HTML;
}

foreach ($Logs as $Log) {
    list($Details, $Text) = $Log;
    if (!empty($Details)) {
        $Details = explode("\r\n", $Details);
        print <<<HTML
    <blockquote>
    <h3>Log validation report:</h3>
    <ul>
HTML;
        foreach ($Details as $Property) {
            print "\t\t<li>{$Property}</li>";
        }
        print <<<HTML
    </ul>
    </blockquote>
HTML;
    }

    echo <<<HTML
    <blockquote>
        <pre>{$Text}</pre>
    </blockquote>
</div>
HTML;

}

View::show_footer();
