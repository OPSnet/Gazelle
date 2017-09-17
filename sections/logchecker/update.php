<?
View::show_header('Logchecker');

print <<<HTML
<div class="linkbox">
	<a href="logchecker.php" class="brackets">Test Logchecker</a>
	<a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
	<a href="logchecker.php?action=update" class="brackets">Update Uploaded Logs</a>
</div>
<div class="thin">
	<h2 class="center">Update Log</h2>
	<div class="box pad">
		<p>
		This form allows you to update the logs for any torrent that you've uploaded.
		Select a torrent and upload the log files in the form <u>below</u>, making sure to add
		all logs that you wish to upload. This will overwrite any previously uploaded logs for
		this torrent. If you wish to just have a torrent manually rescored, please report it
		to staff.
		</p>
		<br />
		<form action="" method="post" enctype="multipart/form-data">
		  <input type="hidden" name="action" value="take_upload" />
		  <table class="form_post vertical_margin">
			<tr class="colhead">
			  <td colspan="2">Select a Torrent</td>
			</tr>
HTML;

$DB->query("
	SELECT t.ID, t.GroupID, t.Format, t.Encoding 
	FROM torrents t
	WHERE t.HasLog='1' AND t.UserID = " . $LoggedUser['ID']);

if ($DB->has_results()) {
	$GroupIDs = $DB->collect('GroupID');
	$TorrentsInfo = $DB->to_array('TorrentID', MYSQLI_ASSOC);
	$Groups = Torrents::get_groups($torrent_ids);

	foreach ($TorrentsInfo as $TorrentID => $Torrent) {
		list($ID, $GroupID, $Format, $Encoding) = $Torrent;
		$Group = $Groups[$GroupID];
		$GroupName = $Group['Name'];
		$GroupYear = $Group['Year'];
		$ExtendedArtists = $Group['ExtendedArtists'];
		$Artists = $Group['Artists'];
		if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
			unset($ExtendedArtists[2]);
			unset($ExtendedArtists[3]);
			$DisplayName = Artists::display_artists($ExtendedArtists);
		} elseif (!empty($Artists)) {
			$DisplayName = Artists::display_artists(array(1 => $Artists));
		} else {
			$DisplayName = '';
		}
		$DisplayName .= '<a href="torrents.php?id='.$GroupID.'&amp;torrentid='.$ID.'" class="tooltip" title="View torrent" dir="ltr">'.$GroupName.'</a>';
		if ($GroupYear > 0) {
			$DisplayName .= " [{$GroupYear}]";
		}
		$Info = array();
		if (!empty($Data['Format'])) {
			$Info[] = $Data['Format'];
		}
		if (!empty($Data['Encoding'])) {
			$Info[] = $Data['Encoding'];
		}
		if (!empty($Info)) {
			$DisplayName .= ' [' . implode('/', $Info) . ']';
		}
		$Output .= "<tr><td style=\"width: 5%;\"><input type=\"radio\" name=\"torrentid\" value=\"$ID\"></td><td>{$DisplayName}</td></tr>";
	}
	$AcceptValues = Logchecker::get_accept_values();
	echo <<<HTML
			{$Output}
			<tr class="colhead">
			  <td colspan="2">Upload Logs for This Torrent</td>
			</tr>
			<tr>
			  <td>
				<input type="file" accept="{$AcceptValues}" name="logfiles[]" size="40" multiple required/>
				<input type="submit" value="Upload Logs!" name="logsubmit" />
			  </td>
			</tr>
HTML;

}
else {
	echo "\t\t\t<tr><td colspan='2'>No uploads found.</td></tr>";
}
print <<<HTML
		  </table>
	</div>
    </form>
</div>
HTML;

View::show_footer();
