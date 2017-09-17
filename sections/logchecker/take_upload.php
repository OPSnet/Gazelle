<?

enforce_login();

$TorrentID = intval($_POST['torrentid']);
$FileCount = count($_FILES['logfiles']['name']);

$LogScore = 100;
$LogChecksum = true;

$DB->query("
	SELECT t.ID, t.GroupID, t.Format, t.Encoding 
	FROM torrents t
	WHERE t.ID = {$TorrentID} AND t.HasLog='1' AND t.UserID = " . $LoggedUser['ID']);

if ($TorrentID != 0 && $DB->has_results() && $FileCount > 0) {
	$DB->query("DELETE FROM torrents_logs WHERE TorrentID='{$TorrentID}'");
	ini_set('upload_max_filesize', 1000000);
	foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
		if (!$_FILES['logfiles']['size'][$Pos]) {
			break;
		}
		$LogFile = file_get_contents($_FILES['logfiles']['tmp_name'][$Pos]);
		//detect & transcode unicode
		if (Logchecker::detect_utf_bom_encoding($LogFile)) {
			$LogFile = iconv("unicode", "UTF-8", $LogFile);
		}
		$Log = new Logchecker();
		$Log->new_file($LogFile, $FileName);
		list($Score, $Details, $Checksum, $LogText) = $Log->parse();
		$Details = implode("\r\n", $Details);
		$LogScore = min($LogScore, $Score);
		$LogChecksum = $LogChecksum && $Checksum;
		$DB->query("INSERT INTO torrents_logs (TorrentID, Log, Details, Score, `Checksum`) VALUES ($TorrentID, '".db_string($LogText)."', '".db_string($Details)."', $Score, '".enum_boolean($Checksum)."')");
	}

	$DB->query("UPDATE torrents SET HasLogDB='1', LogScore={$LogScore}, LogChecksum='".enum_boolean($LogChecksum)."' WHERE ID='{$TorrentID}'");
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

if($Score == 100) {
	$Color = '#418B00';
}
elseif($Score > 90) {
	$Color = '#74C42E';
}
elseif($Score > 75) {
	$Color = '#FFAA00';
}
elseif($Score > 50) {
	$Color = '#FF5E00';
}
else {
	$Color = '#FF0000';
}

echo "<blockquote><strong>Score:</strong> <span style=\"color:$Color\">$Score</span> (out of 100)</blockquote>";

if (!$Checksum) {
	echo <<<HTML
	<blockquote>
		<strong>Trumpable For:</strong>
		<br /><br />
		Bad/No Checksum(s)
	</blockquote>
HTML;
}

$Details = explode("\r\n", $Details);
if(!empty($Details)){
	print <<<HTML
	<blockquote>
	<h3>Log validation report:</h3>
	<ul>
HTML;
	foreach($Details as $Property){
		print "\t\t<li>{$Property}</li>";
	}
	print <<<HTML
	</ul>
	</blockquote>
HTML;
}
echo <<<HTML
	<blockquote>
		<pre>{$LogText}</pre>
	</blockquote>
</div>
HTML;

View::show_footer();

