<?php

if (isset($_GET['userid']) && check_perms('users_view_invites')) {
    if (!is_number($_GET['userid'])) {
        error(403);
    }

    $UserID=$_GET['userid'];
    $Sneaky = true;
} else {
    $UserCount = Users::get_enabled_users_count();
    $UserID = $LoggedUser['ID'];
    $Sneaky = false;
}

list($UserID, $Username, $PermissionID) = array_values(Users::user_info($UserID));

$DB->prepared_query('
    SELECT InviteKey, Email, Expires
    FROM invites
    WHERE InviterID = ?
    ORDER BY Expires
    ', $UserID
);
$Pending = $DB->to_array();
$OrderWays = ['username', 'email', 'joined', 'lastseen', 'uploaded', 'downloaded', 'ratio'];

if (empty($_GET['order'])) {
    $CurrentOrder = 'joined';
    $CurrentSort = 'desc';
    $NewSort = 'asc';
} else {
    if (in_array($_GET['order'], $OrderWays)) {
        $CurrentOrder = $_GET['order'];
        if ($_GET['sort'] == 'asc' || $_GET['sort'] == 'desc') {
            $CurrentSort = $_GET['sort'];
            $NewSort = ($_GET['sort'] == 'asc' ? 'desc' : 'asc');
        } else {
            error(404);
        }
    } else {
        error(404);
    }
}

switch ($CurrentOrder) {
    case 'username':
        $OrderBy = "um.Username";
        break;
    case 'email':
        $OrderBy = "um.Email";
        break;
    case 'joined':
        $OrderBy = "ui.JoinDate";
        break;
    case 'lastseen':
        $OrderBy = "um.LastAccess";
        break;
    case 'uploaded':
        $OrderBy = "uls.Uploaded";
        break;
    case 'downloaded':
        $OrderBy = "uls.Downloaded";
        break;
    case 'ratio':
        $OrderBy = "(uls.Uploaded / uls.Downloaded)";
        break;
    default:
        $OrderBy = "um.ID";
        break;
}

$CurrentURL = Format::get_url(['action', 'order', 'sort']);

$DB->prepared_query("
    SELECT
        um.ID,
        um.Email,
        uls.Uploaded,
        uls.Downloaded,
        ui.JoinDate,
        um.LastAccess
    FROM users_main AS um
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
    INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
    WHERE ui.Inviter = ?
    ORDER BY $OrderBy $CurrentSort
    ", $UserID
);
$Invited = $DB->to_array();

View::show_header('Invites');

?>
<div class="thin">
    <div class="header">
        <h2><?=Users::format_username($UserID, false, false, false)?> &gt; Invites</h2>
        <div class="linkbox">
            <a href="user.php?action=invitetree<?php if ($Sneaky) { echo '&amp;userid='.$UserID; } ?>" class="brackets">Invite tree</a>
        </div>
    </div>
<?php if ($UserCount >= USER_LIMIT && !check_perms('site_can_invite_always')) { ?>
    <div class="box pad notice">
        <p>Because the user limit has been reached you are unable to send invites at this time.</p>
    </div>
<?php }

/*
    Users cannot send invites if they:
        -Are on ratio watch
        -Have disabled leeching
        -Have disabled invites
        -Have no invites (Unless have unlimited)
        -Cannot 'invite always' and the user limit is reached
*/

$DB->prepared_query('
    SELECT can_leech
    FROM users_main
    WHERE ID = ?
    ', $UserID
);
list($CanLeech) = $DB->next_record();

    if (!$Sneaky
        && !$LoggedUser['RatioWatch']
        && $CanLeech
        && empty($LoggedUser['DisableInvites'])
        && ($LoggedUser['Invites'] > 0 || check_perms('site_send_unlimited_invites'))
        && ($UserCount <= USER_LIMIT || USER_LIMIT == 0 || check_perms('site_can_invite_always'))
    ) { ?>
    <div class="box pad">
        <p>Please note that selling, trading, or publicly giving away our invitations&#8202;&mdash;&#8202;or responding to public invite requests&#8202;&mdash;&#8202;is strictly forbidden, and may result in you and your entire invite tree being banned.</p>
        <p>Do not send an invite to anyone who has previously had an <?=SITE_NAME?> account. Please direct them to <?=BOT_DISABLED_CHAN?> on <?=BOT_SERVER?> if they wish to reactivate their account.</p>
        <p>Remember that you are responsible for ALL invitees, and your account and/or privileges may be disabled due to your invitees' actions. You should know and trust the person you're inviting. If you aren't familiar enough with the user to trust them, do not invite them.</p>
        <p><em>Do not send an invite if you have not read or do not understand the information above.</em></p>
    </div>
    <div class="box box2">
        <form class="send_form pad" name="invite" action="user.php" method="post">
            <input type="hidden" name="action" value="take_invite" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <div class="field_div">
                <div class="label">Email address:</div>
                <div class="input">
                    <input type="email" name="email" size="60" />
                    <input type="submit" value="Invite" />
                </div>
            </div>
<?php if (check_perms('users_invite_notes')) { ?>
            <div class="field_div">
                <div class="label">Staff Note:</div>
                <div class="input">
                    <input type="text" name="reason" size="60" maxlength="255" />
                </div>
            </div>
<?php } ?>
        </form>
    </div>

<?php
} elseif (!empty($LoggedUser['DisableInvites'])) { ?>
    <div class="box pad" style="text-align: center;">
        <strong class="important_text">Your invites have been disabled. Please read <a href="wiki.php?action=article&amp;id=116">this article</a> for more information.</strong>
    </div>
<?php
} elseif ($LoggedUser['RatioWatch'] || !$CanLeech) { ?>
    <div class="box pad" style="text-align: center;">
        <strong class="important_text">You may not send invites while on Ratio Watch or while your leeching privileges are disabled. Please read <a href="wiki.php?action=article&amp;id=116">this article</a> for more information.</strong>
    </div>
<?php
}

if (!empty($Pending)) {
?>
    <h3>Pending invites</h3>
    <div class="box pad">
        <table width="100%">
            <tr class="colhead">
                <td>Email address</td>
                <td>Expires in</td>
                <td>Invite link</td>
                <td>Delete invite</td>
            </tr>
<?php
    $Row = 'a';
    foreach ($Pending as $Invite) {
        list($InviteKey, $Email, $Expires) = $Invite;
        $Row = $Row === 'a' ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td><?=display_str($Email)?></td>
                <td><?=time_diff($Expires)?></td>
                <td><a href="register.php?invite=<?=$InviteKey?>">Invite link</a></td>
                <td><a href="user.php?action=delete_invite&amp;invite=<?=$InviteKey?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onclick="return confirm('Are you sure you want to delete this invite?');">Delete invite</a></td>
            </tr>
<?php
    } ?>
        </table>
    </div>
<?php
}

?>
    <h3>Invitee list</h3>
    <div class="box pad">
        <table class="invite_table m_table "width="100%">
            <tr class="colhead">
                <td class="m_th_left"><a href="user.php?action=invite&amp;order=username&amp;sort=<?=(($CurrentOrder == 'username') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Username</a></td>
                <td><a href="user.php?action=invite&amp;order=email&amp;sort=<?=(($CurrentOrder == 'email') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Email</a></td>
                <td><a href="user.php?action=invite&amp;order=joined&amp;sort=<?=(($CurrentOrder == 'joined') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Joined</a></td>
                <td><a href="user.php?action=invite&amp;order=lastseen&amp;sort=<?=(($CurrentOrder == 'lastseen') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Last Seen</a></td>
                <td class="m_th_right"><a href="user.php?action=invite&amp;order=uploaded&amp;sort=<?=(($CurrentOrder == 'uploaded') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Uploaded</a></td>
                <td class="m_th_right"><a href="user.php?action=invite&amp;order=downloaded&amp;sort=<?=(($CurrentOrder == 'downloaded') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Downloaded</a></td>
                <td class="m_th_right"><a href="user.php?action=invite&amp;order=ratio&amp;sort=<?=(($CurrentOrder == 'ratio') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Ratio</a></td>
            </tr>
<?php
    $Row = 'a';
    foreach ($Invited as $User) {
        list($ID, $Email, $Uploaded, $Downloaded, $JoinDate, $LastAccess) = $User;
        $Row = $Row === 'a' ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td class="td_username"><?=Users::format_username($ID, true, true, true, true)?></td>
                <td class="td_email"><?=display_str($Email)?></td>
                <td class="td_join_date"><?=time_diff($JoinDate, 1)?></td>
                <td class="td_last_access"><?=time_diff($LastAccess, 1);?></td>
                <td class="td_up m_td_right"><?=Format::get_size($Uploaded)?></td>
                <td class="td_dl m_td_right"><?=Format::get_size($Downloaded)?></td>
                <td class="td_ratio m_td_right"><?=Format::get_ratio_html($Uploaded, $Downloaded)?></td>
            </tr>
<?php
    } ?>
        </table>
    </div>
</div>
<?php
View::show_footer();
