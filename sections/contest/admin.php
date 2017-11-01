<?php
if (isset($_GET['id']) && is_number($_GET['id'])) {
	$Contest = Contest::get_contest(intval($_GET['id']));
}
else {
	$Contest = Contest::get_current_contest();
}

$Saved = 0;

if (!check_perms('users_mod')) {
	error(403);
}

if (!empty($_POST['name'])) {
    authorize();
    $Id = $Contest['ID'];
    G::$DB->query("
        UPDATE contest SET
            Name       = '".db_string($_POST['name'])."',
            Display    = {$_POST['display']},
            MaxTracked = {$_POST['maxtrack']},
            DateBegin  = '".db_string($_POST['date_begin'])."',
            DateEnd    = '".db_string($_POST['date_end'])."'
        WHERE ID = {$Id}");
    G::$Cache->delete_value('contest_current');
    G::$Cache->delete_value("contest_{$Id}");
    $Contest = Contest::get_contest($Id);
    $Saved = 1;
}

View::show_header('contest admin');
?>
<div class="thin">
	<div class="header">
		<h2>Contest admin</h2>
		<div class="linkbox">
			<a href="contest.php" class="brackets">Intro</a>
			<a href="contest.php?action=leaderboard" class="brackets">Leaderboard</a>
		</div>
	</div>

<?php
if ($Saved) {
	echo "<p>Contest information saved.</p>";
}

G::$DB->query("SELECT ID, Name, DateBegin, DateEnd FROM contest ORDER BY DateBegin");
if (G::$DB->has_results()) {
?>
	<div class="box pad">
		<table>
			<tr class="colhead">
				<td>Name</td>
				<td>Date Begins</td>
				<td>Date Ends</td>
			</tr>
<?php
	while ($Row = G::$DB->next_record()) {
?>
			<tr>
				<td><a href="contest.php?action=admin&id=<?=$Row['ID']?>"><?=$Row['Name']?></a></td>
				<td><?=$Row['DateBegin']?></td>
				<td><?=$Row['DateEnd']?></td>
			</tr>
<?php
	}
?>
		</table>
	</div>
<?php
}

if (!empty($Contest)) {
?>
	<form class="edit_form" name="contest" id="contestform" action="contest.php?action=admin&id=<?= $Contest['ID'] ?>" method="post">
		<table>
			<tr>
				<td class="label">Contest name:</td>
				<td>
					<p>Edit the name of the contest</p>
					<input type="text" size="80" name="name" value="<?= $Contest['Name'] ?>"/>
				</td>
			</tr>

			<tr>
				<td class="label">Begin date:</td>
				<td>
					<p>Uploaded torrents are counted from this date (yyyy/mm/dd hh:mm:ss)</p>
					<input type="text" size="20" name="date_begin" value="<?= $Contest['DateBegin'] ?>"/>
				</td>
			</tr>

			<tr>
				<td class="label">End date:</td>
				<td>
					<p>Uploaded torrents are counted up until this date (yyyy/mm/dd hh:mm:ss)</p>
					<input type="text" size="20" name="date_end" value="<?= $Contest['DateEnd'] ?>"/>
				</td>
			</tr>

			<tr>
				<td class="label">Displayed:</td>
				<td>
					<p>This many people will be displayed on the ladderboard</p>
					<input type="text" size="20" name="display" value="<?= $Contest['Display'] ?>"/>
				</td>
			</tr>

			<tr>
				<td class="label">Max tracked:</td>
				<td>
					<p>Even if a person is not on the displayed ladderboard, we can still tell them
						where they are (this corresponds to an SQL LIMIT value).</p>
					<input type="text" size="20" name="maxtrack" value="<?= $Contest['MaxTracked'] ?>"/>
				</td>
			</tr>
		</table>
		<input type="hidden" name="userid" value="<?= $UserID ?>"/>
		<input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>"/>
		<input type="submit" id="submit" value="Save contest"/>
	</form>
</div>

<?php
}
View::show_footer();
