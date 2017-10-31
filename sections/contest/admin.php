<?php
$CONTEST = contest_config();
$saved = 0;

if (check_perms('users_mod') && !empty($_POST['name'])) {
    authorize();
    $id = $CONTEST[ID];
    G::$DB->query(<<<END_SQL
        UPDATE contest SET
            Name       = "${_POST['name']}",
            Display    = $_POST['display'],
            MaxTracked = $_POST['maxtrack'],
            DateBegin  = "${_POST['dtbegin']}",
            DateEnd    = "${_POST['dtend']}"
        WHERE ID = $id
END_SQL
    );
    G::$Cache->delete_value('contest_current');
    $CONTEST = contest_config();
    $saved = 1;
}
elseif (!check_perms('users_mod') || empty($_GET['theunitadmin']) || !is_number($_GET['theunitadmin']) || $_GET['theunitadmin'] != 1) {
	error(403);
}
View::show_header('contest admin');
?>
<div class="thin">
	<div class="header">
		<h2>Contest admin</h2>
	</div>

<?php
    if ($saved) {
        echo "<p>Contest information saved.</p>";
    }
?>
	<form class="edit_form" name="contest" id="contestform" action="contest.php?theunitadmin=1" method="post">
        <table>

			<tr>
				<td class="label">Contest name:</td>
				<td>
                    <p>Edit the name of the contest</p>
					<input type="text" size="80" name="name" value="<?=$CONTEST[Name]?>" />
				</td>
			</tr>

			<tr>
				<td class="label">Begin date:</td>
				<td>
                    <p>Uploaded torrents are counted from this date (yyyy/mm/dd hh:mm:ss)</p>
					<input type="text" size="20" name="dtbegin" value="<?=$CONTEST[Date_Begin]?>" />
				</td>
			</tr>

			<tr>
				<td class="label">End date:</td>
				<td>
                    <p>Uploaded torrents are counted up until this date (yyyy/mm/dd hh:mm:ss)</p>
					<input type="text" size="20" name="dtend" value="<?=$CONTEST[Date_End]?>" />
				</td>
			</tr>

			<tr>
				<td class="label">Displayed:</td>
				<td>
                    <p>This many people will be displayed on the ladderboard</p>
					<input type="text" size="20" name="display" value="<?=$CONTEST[Displayed]?>" />
				</td>
			</tr>

			<tr>
				<td class="label">Max tracked:</td>
				<td>
                    <p>Even if a person is not on the displayed ladderboard, we can still tell them
                    where they are (this corresponds to an SQL LIMIT value).</p>
					<input type="text" size="20" name="maxtrack" value="<?=$CONTEST[CONTEST_MAXTRACKED]?>" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="userid" value="<?=$UserID?>" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <input type="submit" id="submit" value="Save contest" />
    </form>
</div>
