<?
if (!check_perms('users_mod')) {
	error(403);
}

//Make sure the form was sent
if (isset($_POST['GroupID'])) {
	authorize();

	//Album of the week forum ID
	$ForumID = 43;

	$GroupID = trim($_POST['GroupID']);

	if (!is_number($GroupID)) {
		error(403);
	}

	$DB->query('
		SELECT
			tg.ArtistID,
			tg.Name,
			tg.WikiImage,
			ag.Name AS Artist
		FROM torrents_group AS tg
			LEFT JOIN artists_group AS ag ON tg.ArtistID = ag.ArtistID
		WHERE tg.id = ' . $GroupID);

	$Album = $DB->next_record();

	//Make sure album exists
	if (is_number($Album['ID'])) {

		//Get post title (album title)
		if ($Album['ArtistID'] != '0') {
			$Title = $Album['Artist'] . ' - ' . $Album['Name'];
		} else {
			$Title = $Album['Name'];
		}

		//Get post body
		$Body = '[size=4]' . $Title . '[/size]' . "\n\n";
		if (!empty($Album['WikiImage']))
			$Body .= '[img]' . $Album['WikiImage'] . '[/img]';

		//Create forum post
		$ThreadID = Misc::create_thread($ForumID, $LoggedUser[ID], $Title, $Body);

		//Add album of the week
		$DB->query("
			INSERT INTO featured_albums
				(GroupID,ThreadID,Started)
			VALUES
				('".db_string($GroupID)."', '".db_string($ThreadID)."', '".sqltime()."')");


		//Redirect to home page
		header ("Location: /");

	//What to do if we don't have a GroupID
	} else {

		//Uh oh, something went wrong
		error('Please supply a valid album ID');

	}

//Form wasn't sent -- Show form
} else {

	//Show our beautiful header
	View::show_header('Album of the Week');

	?>
	<div class="header">
		<h2>Album of the Week</h2>
	</div>

	<div class="thin box pad">
	<form class="create_form" name="album" method="post" action="">
		<input type="hidden" name="action" value="weekalbum" />
		<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
		<table class="layout" cellpadding="2" cellspacing="1" border="0" align="center">
			<tr valign="top">
				<td align="right" class="label">Album ID:</td>
				<td align="left"><input type="text" name="GroupID" id="groupid" class="inputtext" /></td>
			</tr>
				<td colspan="2" align="right">
					<input type="submit" name="submit" value="Submit" class="submit" />
				</td>
			</tr>
		</table>
	</form>
	</div>
<?
	
	View::show_footer(); ?>
}
