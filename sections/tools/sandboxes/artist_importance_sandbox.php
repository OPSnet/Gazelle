<?php

if (!check_perms('users_mod')) {
	error(403);
}
$Title = "Artist Importance Sandbox";
View::show_header($Title, 'bbcode_sandbox');
$DB->prepared_query("SELECT aa.Name as ArtistName, tg.Name as GroupName, ta.Importance, ta.GroupID, ta.ArtistID FROM torrents_artists AS ta LEFT JOIN torrents_group AS tg ON tg.ID = ta.GroupID LEFT JOIN artists_alias AS aa ON aa.ArtistID = ta.ArtistID WHERE ta.ArtistID=? ORDER BY ta.ArtistID, ta.GroupID", 353765);

?>
	<div class="header">
		<h2><?=$Title?></h2>
	</div>
	<div class="thin box pad">
		<table>
			<tr>
				<th>Artist Name</th>
				<td>Group Name</td>
				<td>Importance</td>
				<td>Artist Link</td>
				<td>Group Link</td>
			</tr>
<?php

		while (list($ArtistName, $GroupName, $Importance, $GroupID, $ArtistID) = $DB->fetch_record()) {
?>
			<tr>
				<td><?=$ArtistName?></td>
				<td><?=$GroupName?></td>
				<td><?=var_export($Importance, true)?></td>
				<td>https://apollo.rip/torrents.php?id=<?=$GroupID?></td>
				<td>https://apollo.rip/artist.php?id=<?=$ArtistID?></td>
			</tr>
<?php
		}
?>
		</table>
	</div>
<?
View::show_footer();
