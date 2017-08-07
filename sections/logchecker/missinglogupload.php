<?
function detect_utf_bom_encoding($filename) {
	// Unicode BOM is U+FEFF, but after encoded, it will look like this.
	define ('UTF32_BIG_ENDIAN_BOM' , chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF));
	define ('UTF32_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00));
	define ('UTF16_BIG_ENDIAN_BOM' , chr(0xFE) . chr(0xFF));
	define ('UTF16_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE));
	define ('UTF8_BOM' , chr(0xEF) . chr(0xBB) . chr(0xBF));
	$text = file_get_contents($filename);
	$first2 = substr($text, 0, 2);
	$first3 = substr($text, 0, 3);
	$first4 = substr($text, 0, 3);
	if ($first3 == UTF8_BOM) return 'UTF-8';
	elseif ($first4 == UTF32_BIG_ENDIAN_BOM) return 'UTF-32BE';
	elseif ($first4 == UTF32_LITTLE_ENDIAN_BOM) return 'UTF-32LE';
	elseif ($first2 == UTF16_BIG_ENDIAN_BOM) return 'UTF-16BE';
	elseif ($first2 == UTF16_LITTLE_ENDIAN_BOM) return 'UTF-16LE';
}
//ini_set('upload_max_filesize',100000);
require_once(SERVER_ROOT.'/classes/logchecker.class.php');
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
		if (!$File) {
		    die('LogFile doesn\'t exist, or couldn\'t open');
		} // File doesn't exist, or couldn't open
		$LogFile = fread($File, 1000000); // Contents of the log are now stored in $LogFile
		fclose($File);
		//detect & transcode unicode
		if (detect_utf_bom_encoding($_FILES['logfiles']['tmp_name'][$Pos])) {
		    $LogFile = iconv("unicode","UTF-8",$LogFile);
		}
		$Log = new LOG_CHECKER;
		$Log->new_file($LogFile);
		list($Score, $LogGood, $LogBad, $LogText) = $Log->parse();
		if ($LogMinScore === null || $Score < $LogMinScore) {
		    $LogMinScore = $Score;
		}
		//$LogGood = implode("\r\n",$LogGood);
		$LogBad = implode("\r\n",$LogBad);
		$LogNotEnglish = (strpos($LogBad, 'Unrecognized log file')) ? 1 : 0;
		$DB->query("INSERT INTO torrents_logs_new VALUES (null, $TorrentID, '".db_string($LogText)."', '".db_string($LogBad)."', $Score, 1, 0, 0, $LogNotEnglish, '')"); //set log scores
	}
	if ($LogMinScore) { $DB->query("UPDATE torrents SET LogScore='$LogMinScore' WHERE ID=$TorrentID"); } //set main score
} else {
	error('No log file uploaded or no torrent is selected.');
}

View::show_header();
echo '<div class="thin center"><br><a href="javascript:history.go(-1)">Upload another log file</a></div><div class="thin">';
//detect & transcode unicode
if (detect_utf_bom_encoding($FileName)) { $LogFile = iconv("unicode","UTF-8",$LogFile); }

if($Score == 100) { $Color = '#418B00'; }
elseif($Score > 90) { $Color = '#74C42E'; }
elseif($Score > 75) { $Color = '#FFAA00'; }
elseif($Score > 50) { $Color = '#FF5E00'; }
else { $Color = '#FF0000'; }
echo "<blockquote><strong>Score:</strong> <span style=\"color:$Color\">$Score</span> (out of 100)</blockquote>";

if($Bad){
?>
	<blockquote>
	<h3>Log validation report:</h3>
	<ul>
<?
	foreach($Bad as $Property){
?>
		<li><?=$Property?></li>
<?
	}
?>
	</ul>
	</blockquote>
<?
}
echo '<blockquote><pre>';
echo $Text;
echo '</pre></blockquote></div>';
View::show_footer();
?>

