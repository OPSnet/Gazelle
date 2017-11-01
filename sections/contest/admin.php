<?php
$CONTEST = contest_config();
$saved = 0;

if (!check_perms('users_mod')) {
	error(403);
}

if (!empty($_POST['name'])) {
    authorize();
    $id = $CONTEST[ID];
    G::$DB->query("
        UPDATE contest SET
            Name       = '".db_string($_POST['name'])."',
            Display    = {$_POST['display']},
            MaxTracked = {$_POST['maxtrack']},
            DateBegin  = '".db_string($_POST['date_begin'])."',
            DateEnd    = '".db_string($_POST['date_end'])."'
        WHERE ID = {$id}");
    G::$Cache->delete_value('contest_current');
    $CONTEST = contest_config();
    $saved = 1;
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
    if ($saved) {
        echo "<p>Contest information saved.</p>";
    }
?>
	<form class="edit_form" name="contest" id="contestform" action="contest.php?action=admin" method="post">
        <table>

			<tr>
				<td class="label">Contest name:</td>
				<td>
                    <p>Edit the name of the contest</p>
					<input type="text" size="80" name="name" value="<?=$CONTEST['Name']?>" />
				</td>
			</tr>

			<tr>
				<td class="label">Begin date:</td>
				<td>
                    <p>Uploaded torrents are counted from this date (yyyy/mm/dd hh:mm:ss)</p>
					<input type="text" size="20" name="date_begin" value="<?=$CONTEST['DateBegin']?>" />
				</td>
			</tr>

			<tr>
				<td class="label">End date:</td>
				<td>
                    <p>Uploaded torrents are counted up until this date (yyyy/mm/dd hh:mm:ss)</p>
					<input type="text" size="20" name="date_end" value="<?=$CONTEST['DateEnd']?>" />
				</td>
			</tr>

			<tr>
				<td class="label">Displayed:</td>
				<td>
                    <p>This many people will be displayed on the ladderboard</p>
					<input type="text" size="20" name="display" value="<?=$CONTEST['Display']?>" />
				</td>
			</tr>

			<tr>
				<td class="label">Max tracked:</td>
				<td>
                    <p>Even if a person is not on the displayed ladderboard, we can still tell them
                    where they are (this corresponds to an SQL LIMIT value).</p>
					<input type="text" size="20" name="maxtrack" value="<?=$CONTEST['MaxTracked']?>" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="userid" value="<?=$UserID?>" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <input type="submit" id="submit" value="Save contest" />
    </form>
</div>
