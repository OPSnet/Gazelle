<?php
if (isset($_GET['id']) && is_number($_GET['id'])) {
    $Contest = Contest::get_contest(intval($_GET['id']));
} else {
    $Contest = Contest::get_current_contest();
}

$Saved = 0;

if (!check_perms('users_mod')) {
    error(403);
}

if (!empty($_POST['name'])) {
    authorize();
    Contest::save($_POST);
    $Contest = Contest::get_contest($_POST['cid']);
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

Contest::init_admin();

G::$DB->query("
	SELECT c.ID, c.Name, c.DateBegin, c.DateEnd, t.ID as ContestType
	FROM contest c
	INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
	ORDER BY c.DateBegin
 ");
if (G::$DB->has_results()) {
?>
    <div class="box pad">
        <table>
            <tr class="colhead">
                <td>Name</td>
                <td>Contest Type</td>
                <td>Date Begins</td>
                <td>Date Ends</td>
            </tr>
<?php
while ($Row = G::$DB->next_record()) {
?>
    <tr>
        <td><a href="contest.php?action=admin&id=<?=$Row['ID']?>"><?=$Row['Name']?></a></td>
        <td><?= Contest::contest_type()[$Row['ContestType']] ?></td>
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

if (!empty($Contest)) { { { { { { { { { { { { { { { { { { { { { { { { { { {
if ($Contest['ContestType'] === 'request_fill') {
?>
<div class="box pad">
<h2>Request pairs</h2>
<?
$Pairs = Contest::get_request_pairs();
if (!count($Pairs)) {
?>
<p>No members have filled out more than one request for the same member.</p>
<?
}
else {
?>
<p>The following members have filled out more than one request for the same member.</p>
<table>
    <tr class="colhead">
        <td>Request filler</td>
        <td>Request creator</td>
        <td>Filled</td>
    </tr>
<?
    foreach ($Pairs as $p) {
        $filler  = Users::user_info($p['FillerID']);
        $creator = Users::user_info($p['UserID']);
?>
    <tr>
        <td><?= $filler['Username'] ?></td>
        <td><?= $creator['Username'] ?></td>
        <td><?= $p['nr'] ?></td>
    </tr>
<?
    }
?>
</table>
<?
}
?>
</div>
<?
} /* request_fill */
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
        <td class="label">Contest type:</td>
        <td>
            <p>Edit the type of the contest</p>
            <select name="type">
<?
                foreach (Contest::contest_type() as $id => $name) {
                    printf('                    <option value="%d"%s>%s</option>',
                        $id,
                        ($name == $Contest['ContestType']) ? ' selected' : '',
                        $name
                    );
                }
?>
            </select>
        </td>
    </tr>

    <tr>
        <td class="label">Begin date:</td>
        <td>
            <p>Uploaded torrents/completed requests are counted from this date (yyyy/mm/dd hh:mm:ss)</p>
            <input type="text" size="20" name="date_begin" value="<?= $Contest['DateBegin'] ?>"/>
        </td>
    </tr>

    <tr>
        <td class="label">End date:</td>
        <td>
            <p>Uploaded torrents/completed requests are counted up until this date (yyyy/mm/dd hh:mm:ss)</p>
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

    <tr>
        <td class="label">Banner:</td>
        <td>
            <p>This is the image displayed at the top of the page (optional).
               May be a local asset, or a URL.</p>
            <input type="text" size="60" name="banner" value="<?= $Contest['Banner'] ?>"/>
        </td>
    </tr>

    <tr>
        <td class="label">Introduction:</td>
        <td>
            <p>This is the introduction / guide of the contest.</p>
            <?php $IntroText = new TEXTAREA_PREVIEW('intro', 'intro', display_str($Contest['WikiText']), 60, 8, true, false); ?>
            <div style="text-align: center;">
                <input type="button" value="Preview" class="hidden button_preview_<?=$IntroText->getID()?>" tabindex="1" />
            </div>
        </td>
    </tr>

</table>
<input type="hidden" name="userid" value="<?= $UserID ?>"/>
<input type="hidden" name="cid" value="<?= $Contest['ID'] ?>"/>
<input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>"/>
<input type="submit" id="submit" value="Save contest"/>
</form>
</div>

<?php
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
}
} /* !empty($Contest) */
View::show_footer();
