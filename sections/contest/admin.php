<?php

if (!check_perms('admin_manage_contest')) {
    error(403);
}

$Create = isset($_GET['action']) && $_GET['action'] == 'create';
$Saved = 0;
if (!empty($_POST['new']) || !empty($_POST['cid'])) {
    authorize();
    $Contest = $ContestMgr->get_contest($ContestMgr->save($_POST));
    $Saved = 1;
}
elseif (isset($_GET['id']) && intval($_GET['id'])) {
    $Contest = $ContestMgr->get_contest(intval($_GET['id']));
}
elseif (!$Create) {
    $Contest = $ContestMgr->get_current_contest();
}

View::show_header('contest admin');
?>
<div class="thin">
    <div class="header">
        <h2>Contest admin</h2>
        <div class="linkbox">
            <a href="contest.php" class="brackets">Intro</a>
            <a href="contest.php?action=leaderboard" class="brackets">Leaderboard</a>
<?php
if (!$Create) { ?>
            <a href="contest.php?action=create" class="brackets">Create</a>
<?php
} else { ?>
            <a href="contest.php?action=admin" class="brackets">Admin</a>
<?php
} ?>
        </div>
    </div>

<?php
if ($Saved) {
    echo "<p>Contest information saved.</p>";
}
$ContestType = $ContestMgr->get_type();

if (!$Create) {
    $ContestList = $ContestMgr->get_list();
    if (count($ContestList)) {
?>
    <div class="box pad">
        <table>
            <tr class="colhead">
                <td>Name</td>
                <td>Contest Type</td>
                <td>Begins</td>
                <td>Ends</td>
            </tr>
<?php
        foreach ($ContestList as $Row) {
?>
            <tr>
                <td><?= $Contest['ID'] == $Row['ID'] ? urldecode('%E2%98%9E') . '&nbsp;' : '' ?><a href="contest.php?action=admin&id=<?=$Row['ID']?>"><?=$Row['Name']?></a></td>
                <td><?= $ContestType[$Row['ContestType']]['Name'] ?></td>
                <td><?=$Row['DateBegin']?></td>
                <td><?=$Row['DateEnd']?></td>
            </tr>
<?php
        }
?>
        </table>
    </div>
<?php
    } /* (count($ContestList)) */
} /* !$Create */

if ($Create || !empty($Contest)) {
    if ($Contest['ContestType'] === 'request_fill') {
?>
    <div class="box pad">
        <h2>Request pairs</h2>
<?php
        $Pairs = $ContestMgr->get_request_pairs();
        if (!count($Pairs)) {
?>
        <p>No members have filled out more than one request for the same member.</p>
<?php
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
<?php
            foreach ($Pairs as $p) {
                $filler  = Users::user_info($p['FillerID']);
                $creator = Users::user_info($p['UserID']);
?>
            <tr>
                <td><?= $filler['Username'] ?></td>
                <td><?= $creator['Username'] ?></td>
                <td><?= $p['nr'] ?></td>
            </tr>
<?php
            }
?>
        </table>
<?php
        }
?>
    </div>
<?php
    } /* request_fill */ ?>
    <form class="edit_form" name="contest" id="contestform" action="<?= $Create ? 'contest.php?action=create' : 'contest.php?action=admin&amp;id=' . $Contest['ID'] ?>" method="post">
<?php
if ($Contest['BonusPool']) {
    $Contest = $ContestMgr->get_contest($Contest['ID']);
    $total = $ContestMgr->calculate_pool_payout($Contest['ID']);
    $bonus = $total['bonus'];
?>
    <div class="box pad">
        <table>
            <tr><th>Payout</th><th>Value</th></tr>
            <tr><td>Enabled users</td><td><?= number_format(Users::get_enabled_users_count()) ?></td></tr>
            <tr><td>Enabled user bonus</td><td><?= number_format($bonus * 0.05 / Users::get_enabled_users_count(), 2) ?></td></tr>
            <tr><td>Contest participation</td><td><?= number_format($bonus * 0.1 / $total['user'], 2) ?></td></tr>
            <tr><td>Per entry added</td><td><?= number_format($bonus * 0.85 / $total['torrent'], 2) ?></td></tr>
            <tr><td>Status of payout</td><td><?= $Contest['BonusStatus'] ?></td></tr>
<?php if ($Contest['payout_ready']) { ?>
            <tr><td>Payout is ready</td><td><input type="submit" name="payment" value="Initiate payment"/></td></tr>
<?php } ?>
        </table>
    </div>
<?php
} /* BonusPool */ ?>
        <table>
            <tr>
                <td class="label">Contest name:</td>
                <td>
                    <p>Edit the name of the contest</p>
                    <input type="text" size="80" name="name" value="<?= $Create ? '' : $Contest['Name'] ?>"/>
                </td>
            </tr>

            <tr>
                <td class="label">Contest type:</td>
                <td>
                    <p>Edit the type of the contest</p>
                    <select name="type">
<?php
                        foreach ($ContestType as $t) {
                            printf('                    <option value="%d"%s>%s</option>',
                                $t['ID'],
                                (!$Create && $t['Name'] == $Contest['ContestType']) ? ' selected' : '',
                                $t['Name']
                            );
                        }
?>
                    </select>
                </td>
            </tr>

            <tr>
                <td class="label">Bonus Point pool:</td>
                <td>
                    <p>Members can contribute their Bonus Points to an award pool</p>
                    <input type="checkbox" name="pool" value="<?= $Create ? 0 : $Contest['BonusPool'] ?>"<?= $Contest['BonusPool'] ? ' checked' : '' ?>/>
                </td>
            </tr>

            <tr>
                <td class="label">Begin date:</td>
                <td>
                    <p>Uploaded torrents/completed requests are counted from this date (yyyy/mm/dd hh:mm:ss)</p>
                    <input type="text" size="20" name="date_begin" value="<?= $Create ? '' : $Contest['DateBegin'] ?>"/>
                </td>
            </tr>

            <tr>
                <td class="label">End date:</td>
                <td>
                    <p>Uploaded torrents/completed requests are counted up until this date (yyyy/mm/dd hh:mm:ss)</p>
                    <input type="text" size="20" name="date_end" value="<?= $Create ? '' : $Contest['DateEnd'] ?>"/>
                </td>
            </tr>

            <tr>
                <td class="label">Displayed:</td>
                <td>
                    <p>This many people will be displayed on the ladderboard</p>
                    <input type="text" size="20" name="display" value="<?= $Create ? 100 : $Contest['Display'] ?>"/>
                </td>
            </tr>

            <tr>
                <td class="label">Max tracked:</td>
                <td>
                    <p>Even if a person is not on the displayed ladderboard, we can still tell them
                        where they are (this corresponds to an SQL LIMIT value).</p>
                    <input type="text" size="20" name="maxtrack" value="<?= $Create ? 2500 : $Contest['MaxTracked'] ?>"/>
                </td>
            </tr>

            <tr>
                <td class="label">Banner:</td>
                <td>
                    <p>This is the image displayed at the top of the page (optional).
                       May be a local asset, or a URL.</p>
                    <input type="text" size="60" name="banner" value="<?= $Create ? '' : $Contest['Banner'] ?>"/>
                </td>
            </tr>

            <tr>
                <td class="label">Introduction:</td>
                <td>
                    <p>This is the introduction / guide of the contest.</p>
                    <?php $IntroText = new TEXTAREA_PREVIEW('intro', 'intro', $Create ? '' : display_str($Contest['WikiText']), 60, 8, true, false); ?>
                    <div style="text-align: center;">
                        <input type="button" value="Preview" class="hidden button_preview_<?=$IntroText->getID()?>" tabindex="1" />
                    </div>
                </td>
            </tr>

        </table>
        <input type="hidden" name="userid" value="<?= $LoggedUser['ID'] ?>"/>
        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>"/>
<?php
if ($Create) { ?>
        <input type="hidden" name="new" value="1"/>
        <input type="submit" id="submit" value="Create contest"/>
<?php
} else { ?>
        <input type="hidden" name="cid" value="<?= $Contest['ID'] ?>"/>
        <input type="submit" id="submit" value="Save contest"/>
<?php
} ?>
    </form>
</div>

<?php
} /* !empty($Contest) */
View::show_footer();
