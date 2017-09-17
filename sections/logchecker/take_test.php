<?
//******************************************************************************//
//--------------- Take upload --------------------------------------------------//
// This pages handles the backend of the torrent upload function. It checks //
// the data, and if it all validates, it builds the torrent file, then writes //
// the data to the database and the torrent to the disk. //
//******************************************************************************//
//ini_set('upload_max_filesize',1000000);
enforce_login();
$File = (isset($_FILES['log'])) ? $_FILES['log'] : null; // Our log file
$FileName = $File['tmp_name'];
if (is_uploaded_file($FileName) && filesize($FileName)) {
	$LogFile = file_get_contents($FileName);
	// Contents of the log are now stored in $LogFile
} elseif (!empty($_POST["pastelog"])) {
	$LogFile = $_POST["pastelog"];
} else {
	error('No log file uploaded or file is empty.');
}


View::show_header('Logchecker');

echo <<<HTML
<div class="linkbox">
	<a href="javascript:history.go(-1)" class="brackets">Test Another Log File</a>
	<a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
</div>
<div class="thin">
	<h2 class="center">Logchecker Test Results</h2>
HTML;

//detect & transcode unicode
if (Logchecker::detect_utf_bom_encoding($LogFile)) {
	$LogFile = iconv("unicode", "UTF-8", $LogFile);
}

$Log = new Logchecker();
$Log->new_file($LogFile, $FileName);

list($Score, $Good, $Bad, $Text, $Checksum) = $Log->parse();

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

if (!$Checksum) {
	echo <<<HTML
	<blockquote>
		<strong>Trumpable For:</strong>
		<br /><br />
		Bad/No Checksum(s)
	</blockquote>
HTML;
}

echo <<<HTML
	<blockquote>
		<strong>Score:</strong> <span style='color:{$Color}'>{$Score}</span> (out of 100)
	</blockquote>
HTML;

if($Bad){
	echo <<<HTML
	<blockquote>
		<h3>Log validation report:</h3>
		<ul>
HTML;
	foreach($Bad as $Property) {
		echo "\t\t\t<li>{$Property}</li>";
	}
echo <<<HTML
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

if (!empty($FileName) && is_file($FileName)) {
	unlink($FileName);
}
