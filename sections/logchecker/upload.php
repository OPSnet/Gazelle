<?
View::show_header('Logchecker'); 
$DB->query("
SELECT t.ID, g.Name as AlbumName, a.Name as ArtistName, g.Year, t.Format, t.Encoding 
FROM torrents t 
JOIN torrents_group g ON t.GroupID = g.ID 
JOIN torrents_artists ta ON g.ID = ta.GroupID 
JOIN artists_group a ON a.ArtistID = ta.ArtistID 
WHERE t.HasLog='1' AND t.LogScore=0 AND t.UserID = " . $LoggedUser['ID']);
        
if ($DB->has_results()) {
	$output = '';
	while (list($ID, $AlbumName, $ArtistName, $Year, $Format, $Encoding) = $DB->next_record()) {
		$output .= "<tr><td style=\"width: 5%\"><input type=\"radio\" name=\"torrentid\" value=\"$ID\"></td><td><a href=\"/torrents.php?torrentid=$ID\">$ArtistName - $AlbumName [$Year] [$Format/$Encoding]</a></td></tr>";	
	}
}
?>
<div class="thin">
	<h2 class="center">Apollo Logchecker: dBpoweramp, EAC and xld.</h2>
	<table class="forum_post vertical_margin">
		<tr class="colhead">
			<td colspan="2">Upload file</td>
		</tr>
		<tr>
			<td>
				<form action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="takeupload" />
					<input type="file" name="log" size="40" />
					<input type="submit" value="Upload log" name="submit" />
				</form>
			</td>
		</tr>
		<tr class="colhead">
			<td colspan="2">Paste log</td>
		</tr>
		<tr>
			<td>
				<form action="" method="post">
					<input type="hidden" name="action" value="takeupload" />
					<textarea rows="5" cols="60" name="pastelog" wrap="soft"></textarea>
					<input type="submit" value="Upload log" name="submit" />
				</form>
			</td>
		</tr>
	</table>
	<br />
	<br />
</div>
<div class="thin">
	<h2 class="center">Missing Log</h2>
	<p>Uploads with logs, but no score/info. Fix this by selecting an unscored torrent and upload the log files in the form <u>below</u> (Please select all logs at once).</p><br>
	<p><a href="?action=snatched">Click here</a> to upload log files for your snatched torrents.</p><br>
    <form action="" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="missinglogupload" />
      <table class="form_post vertical_margin">
        <tr class="colhead">
          <td colspan="2">Select a Torrent</td>
        </tr>
        	<?= $output ?>        
        <tr class="colhead">
          <td colspan="2">Upload Logs for This Torrent</td>
        </tr>
        <tr>
          <td>
            <input type="file" accept=".log,.txt" name="logfiles[]" size="40" multiple required/>
            <input type="submit" value="Upload Logs!" name="logsubmit" />
          </td>
        </tr>
      </table>
	<br />
	<br />
    </form>
</div>
<? View::show_footer(); ?>

