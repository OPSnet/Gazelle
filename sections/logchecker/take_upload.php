<?

enforce_login();

$TorrentID = intval($_POST['torrentid']);
$FileCount = count($_FILES['logfiles']['name']);

$LogScore = 100;
$LogChecksum = 1;

$Extra = check_perms('users_mod') ? '' : " AND t.UserID = '{$LoggedUser['ID']}'";
$DB->query("
	SELECT t.ID, t.GroupID
	FROM torrents t
	WHERE t.ID = {$TorrentID} AND t.HasLog='1'" . $Extra);

$DetailsArray = [];
$Logchecker = new Logchecker();
if ($TorrentID != 0 && $DB->has_results() && $FileCount > 0) {
	list($TorrentID, $GroupID) = $DB->next_record(MYSQLI_BOTH);
	$DB->query("SELECT LogID FROM torrents_logs WHERE TorrentID='{$TorrentID}'");
	while(list($LogID) = $DB->next_record(MYSQLI_NUM)) {
		@unlink(SERVER_ROOT . "/logs/{$TorrentID}_{$LogID}.log");
	}
	$DB->query("DELETE FROM torrents_logs WHERE TorrentID='{$TorrentID}'");
	ini_set('upload_max_filesize', 1000000);
	foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
		if (!$_FILES['logfiles']['size'][$Pos]) {
			break;
		}
		$FileName = $_FILES['logfiles']['name'][$Pos];
		$LogPath = $_FILES['logfiles']['tmp_name'][$Pos];
		$Logchecker->new_file($LogPath);
		list($Score, $Details, $Checksum, $LogText) = $Logchecker->parse();
		$Details = trim(implode("\r\n", $Details));
		$DetailsArray[] = $Details;
		$LogScore = min($LogScore, $Score);
		$LogChecksum = min(intval($Checksum), $LogChecksum);
		$Logs[] = array($Details, $LogText);
		$DB->query("INSERT INTO torrents_logs (TorrentID, Log, Details, Score, `Checksum`, `FileName`) VALUES ($TorrentID, '".db_string($LogText)."', '".db_string($Details)."', $Score, '".enum_boolean($Checksum)."', '".db_string($FileName)."')");
		$LogID = $DB->inserted_id();
		if (move_uploaded_file($LogPath, SERVER_ROOT . "/logs/{$TorrentID}_{$LogID}.log") === false) {
			die("Could not copy logfile to the server.");
		}
	}

	$DB->query("UPDATE torrents SET HasLogDB='1', LogScore={$LogScore}, LogChecksum='".enum_boolean($LogChecksum)."' WHERE ID='{$TorrentID}'");
	$Cache->delete_value("torrent_group_{$GroupID}");
	$Cache->delete_value("torrents_details_{$GroupID}");
} else {
	error('No log file uploaded or no torrent is selected.');
}

View::show_header();
echo <<<HTML
<div class="thin center">
	<br><a href="javascript:history.go(-1)">Upload another log file</a>
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

