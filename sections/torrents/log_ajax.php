<?
enforce_login();
$TorrentID = (int) $_GET['torrentid'];
if (!isset($TorrentID) || empty($TorrentID)) {
	error(403);
}
$LogScore = isset($_GET['logscore']) ? intval($_GET['logscore']) : 0;
$DB->query("SELECT LogId, Log, Details, Score, Checksum, Adjusted, AdjustedBy, AdjustedScore, AdjustmentReason, AdjustmentDetails FROM torrents_logs WHERE TorrentID = '$TorrentID'");
$Logs = $DB->to_array();
if(count($Logs) > 0) {
	ob_start();
	echo '<table><tr class=\'colhead_dark\' style=\'font-weight: bold;\'><td>This torrent has '.count($Logs).' '.(count($Logs) > 1 ?'logs' : 'log').' with a total score of '.$LogScore.' (out of 100):</td></tr>';

    if (check_perms('torrents_delete')) {
        echo "<tr class=\'colhead_dark\' style=\'font-weight: bold;\'><td style='text-align:right;'>
			<a onclick=\"return confirm('This is permanent and irreversible. Missing logs can still be uploaded.');\" href='torrents.php?action=removelogs&amp;torrentid=".$TorrentID."'>Remove all logs</a>
	    </td></tr>";
    }

	foreach($Logs as $Log) {
		list($LogID, $Log, $Details, $Score, $Checksum, $Adjusted, $AdjustedBy, $AdjustedScore, $LogAdjustmentReason, $AdjustmentDetails) = $Log;
		echo '<tr class=\'log_section\'><td>';

        if ($Adjusted === '1') {
			$LogAdjustmentReason = ($LogAdjustmentReason) ? ': '.$LogAdjustmentReason : '';
			echo '<blockquote>Log adjusted by '.Users::format_username($LogAdjustedBy).$LogAdjustmentReason.'</blockquote>';
			$AdjustedDetails = explode("\r\n", $AdjustedDetails);
		}
		if (!empty($Details)) {
			$Details = explode("\r\n", $Details);
			echo '<blockquote><h3>Log validation report:</h3><ul>';
			foreach($Details as $Entry) {
				echo '<li>'.$Entry.'</li>';
			}
			echo '</ul></blockquote>';
		}

		echo "<blockquote><pre style='white-space:pre-wrap;'>".html_entity_decode($Log)."</pre></blockquote>";
        echo '</td></tr>';
	}
	echo '</table>';
	echo ob_get_clean();
} else {
	echo '';
}
