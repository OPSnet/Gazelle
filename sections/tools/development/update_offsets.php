<?php

$Inserted = false;
if (isset($_REQUEST['update']) && $_REQUEST['update'] === '1') {
	$CH = curl_init();

	curl_setopt($CH, CURLOPT_URL, 'http://www.accuraterip.com/driveoffsets.htm');
	curl_setopt($CH, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($CH, CURLOPT_CONNECTTIMEOUT, 5);
	$Doc = new DOMDocument();
	$Doc->loadHTML(curl_exec($CH), LIBXML_NOWARNING | LIBXML_NOERROR);
	curl_close($CH);

	$Rows = $Doc->getElementsByTagName('table')->item(1)->getElementsByTagName('tr');
	$Offsets = [];
	$Prepared = [];
	for ($I = 1; $I < $Rows->length; $I++) {
		$Row = $Rows->item($I);
		if ($Row->childNodes->length > 4 && $Row->childNodes->item(3)->nodeValue !== '[Purged]') {
			$Offsets[] = trim($Row->childNodes->item(1)->nodeValue, '- ');
			$Offsets[] = trim($Row->childNodes->item(3)->nodeValue);
			$Prepared[] = "(?, ?)";
		}
	}

	G::$DB->prepared_query('TRUNCATE drives');
	G::$DB->prepared_query('INSERT INTO drives (Name, Offset) VALUES '.implode(', ', $Prepared), ...$Offsets);
	$Inserted = G::$DB->affected_rows();
}

View::show_header('Update Drive Offsets');

?>
<div class="header">
	<h2>Drive Offsets</h2>
</div>
<div class="thin">
	<div class="box pad">
		<p>This page lists all of the stored Drive Offsets on Orpheus. We use these offsets to check
		the offset listed in a rip log file. This information comes from
		<a href="http://www.accuraterip.com/driveoffsets.htm" target="_blank" rel="noreferrer nofollow">Accuraterip</a>.
		<?=($Inserted !== false) ? "<br />{$Inserted} offsets inserted." : ""?>
		</p>
		<p>
			<a href="tools.php?action=update_offsets&update=1">Update Offsets</a>
		</p>
	</div>
	<table width="100%">
		<tr class="colhead">
			<td>Drive</td>
			<td>Offset</td>
		</tr>
<?php

G::$DB->prepared_query('SELECT Name, Offset FROM drives ORDER BY DriveID');
while (list($Name, $Offset) = G::$DB->fetch_record()) {
	?>
		<tr>
			<td><?=$Name?></td>
			<td><?=$Offset?></td>
		</tr>
	<?php
}
?>
	</table>
</div>
<?php
View::show_footer();