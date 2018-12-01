<?
View::show_header('Logchecker');
/*
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
*/

$AcceptValues = Logchecker::get_accept_values();

echo <<<HTML
<div class="linkbox">
	<a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
	<a href="logchecker.php?action=update" class="brackets">Update Uploaded Logs</a>
</div>
<div class="thin">
	<h2 class="center">Orpheus Logchecker: EAC and XLD.</h2>
	<div class="box pad">
		<p>
		Use this page to test our logchecker. You can either upload a log or paste it into the
		text box below. This will then run the file/text against our logchecker displaying to you
		what it would look like on our site. To verify checksum, you need to upload log file.
		</p>
		<table class="forum_post vertical_margin">
			<tr class="colhead">
				<td colspan="2">Upload file</td>
			</tr>
			<tr>
				<td>
					<form action="" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="take_test" />
						<input type="file" accept="{$AcceptValues}" name="log" size="40" />
						<input type="submit" value="Upload log" name="submit" />
					</form>
				</td>
			</tr>
		</table>
		<table class="forum_post vertical_margin">
			<tr class="colhead">
				<td colspan="2">Paste log (No checksum verification)</td>
			</tr>
			<tr>
				<td>
					<form action="" method="post">
						<input type="hidden" name="action" value="take_test" />
						<textarea rows="20" style="width: 99%" name="pastelog" wrap="soft"></textarea>
						<br /><br />
						<input type="submit" value="Upload log" name="submit" />
					</form>
				</td>
			</tr>
		</table>
	</div>
</div>
HTML;

View::show_footer();
