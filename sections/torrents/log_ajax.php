<?
enforce_login();
$TorrentID = (int) $_GET['torrentid'];
if (!isset($TorrentID) || empty($TorrentID)) { error(403); }
$LogScore = 0+$_GET['logscore'];
$LogScore = ($LogScore) ? $LogScore : '0';
$DB->query("SELECT LogID, Log, Details, Revision, Adjusted, AdjustedBy, Score, NotEnglish, AdjustmentReason FROM torrents_logs_new WHERE TorrentID = '$TorrentID'");
$Logs = $DB->to_array();
if(count($Logs) > 0) {
	ob_start();
	echo '<table><tr class=\'colhead_dark\' style=\'font-weight: bold;\'><td>This torrent has '.count($Logs).' '.(count($Logs) > 1 ?'logs' : 'log').' with a total score of '.$LogScore.' (out of 100):</td></tr>';
	if ($LogScore < 100) {
		//echo '<td><strong>Please note: Score below 100 doesn\'t necessarily mean inferior (sound) quality!</strong></td>';
	}
	foreach($Logs as $Log) {
		list($LogID, $LogTxt, $LogDetails, $LogRevision, $LogAdjusted, $LogAdjustedBy, $LogSScore, $LogNotEnglish, $LogAdjustmentReason) = $Log;
		echo '<tr class=\'log_section\'><td>';
        echo "<div style='text-align:right;'><a href='torrents.php?action=removelogs&amp;torrentid=".$TorrentID."'>Remove log</a></div>";

        if ($LogAdjusted) { //todo: move query out of loop, i.e. check only once
			$DB->query("SELECT Username FROM users_main WHERE ID = $LogAdjustedBy");
			list($LogAdjustedByUser) = $DB->next_record();
			$LogAdjustmentReason = ($LogAdjustmentReason) ? ': '.$LogAdjustmentReason : '';
			echo '<blockquote>Log adjusted by '.format_username($LogAdjustedBy, $LogAdjustedByUser).$LogAdjustmentReason.'</blockquote>';
		}
		if ($LogNotEnglish) {
			echo '<blockquote><strong>Unrecognized log</strong></blockquote>';
		}
		if ($LogDetails) {
			$LogDetails = explode("\r\n", $LogDetails);
			echo '<blockquote><h3>Log validation report:</h3><ul>';
			foreach($LogDetails as $LogDetailsEntry) { echo '<li>'.$LogDetailsEntry.'</li>'; }
			echo '</ul></blockquote>';
		}

		echo "<blockquote><pre style='white-space:pre-wrap;'>".html_entity_decode($LogTxt)."</pre></blockquote>";
        echo '</td></tr>';
	}
	echo '</table>';
	echo ob_get_clean();
} else {
	echo '';
}
?>
