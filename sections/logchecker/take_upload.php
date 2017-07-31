<?

enforce_login();

$TorrentID = 0 + $_POST['torrentid'];
$FileCount = count($_FILES['logfiles']['name']);

if ($TorrentID != 0 && $FileCount > 0) {
	ini_set('upload_max_filesize',1000000);
	$LogMinScore = null;
	foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
		if (!$_FILES['logfiles']['size'][$Pos]) {
			break;
		}
		//todo: more validation
		$File = fopen($_FILES['logfiles']['tmp_name'][$Pos], 'rb'); // open file for reading
		// File doesn't exist, or couldn't open
		if (!$File) {
			die('LogFile doesn\'t exist, or couldn\'t open');
		}
		$LogFile = fread($File, 1000000); // Contents of the log are now stored in $LogFile
		fclose($File);
		//detect & transcode unicode
		if (LOG_CHECKER::detect_utf_bom_encoding($_FILES['logfiles']['tmp_name'][$Pos])) {
			$LogFile = iconv("unicode","UTF-8",$LogFile);
		}
		$Log = new LOG_CHECKER;
		$Log->new_file($LogFile);
		list($Score, $LogGood, $LogBad, $LogText) = $Log->parse();
		if (!$LogMinScore || $Score < $LogMinScore) {
			$LogMinScore = $Score;
		}
		//$LogGood = implode("\r\n",$LogGood);
		$LogBad = implode("\r\n",$LogBad);
		$LogNotEnglish = (strpos($LogBad, 'Unrecognized log file')) ? 1 : 0;
		$DB->query("INSERT INTO torrents_logs_new VALUES (null, $TorrentID, '".db_string($LogText)."', '".db_string($LogBad)."', $Score, 1, 0, 0, $LogNotEnglish, '')"); //set log scores
	}
	if ($LogMinScore) {
		$DB->query("UPDATE torrents SET LogScore='$LogMinScore' WHERE ID=$TorrentID");
	} //set main score
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

if($Bad){
print <<<HTML
	<blockquote>
	<h3>Log validation report:</h3>
	<ul>
HTML;
	foreach($Bad as $Property){
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

View::show_footer();

