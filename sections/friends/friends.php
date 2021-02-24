<?php
/************************************************************************
//------------// Main friends page //----------------------------------//
This page lists a user's friends.

There's no real point in caching this page. I doubt users load it that
much.
************************************************************************/

$UserID = $LoggedUser['ID'];

$Where = "";

[$Page, $Limit] = Format::page_limit(FRIENDS_PER_PAGE);

$Results = $DB->scalar("
    SELECT count(*) FROM friends WHERE UserID = ?
    ", $UserID
);
$Pages = Format::get_pages($Page, $Results, FRIENDS_PER_PAGE, 9);

// Main query
$DB->prepared_query("
    SELECT f.FriendID,
        f.Comment,
        um.Username,
        uls.Uploaded,
        uls.Downloaded,
        um.PermissionID,
        um.Paranoia,
        ula.last_access,
        i.Avatar
    FROM friends AS f
    INNER JOIN users_main AS um ON (um.ID = f.FriendID)
    INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
    INNER JOIN users_info AS i ON (i.UserID = f.FriendID)
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = f.FriendID)
    WHERE f.UserID = ?
    ORDER BY um.Username
    LIMIT ?
    ", $UserID, $Limit
);
$Friends = $DB->to_array(false, MYSQLI_BOTH, [6, 'Paranoia']);

View::show_header('Friends','comments');
?>
<div class="thin">
    <div class="header">
        <h2>Friends List</h2>
    </div>
    <div class="linkbox">
        <?= $Pages ?>
    </div>
    <div class="box pad">
<?php if ($Results == 0) { ?>
       <p>You have no friends! :(</p>
<?php
}
$userMan = new Gazelle\Manager\User;
$user = $userMan->findById($LoggedUser['ID']);

// Start printing out friends
foreach ($Friends as $Friend) {
    [$FriendID, $Comment, $Username, $Uploaded, $Downloaded, $Class, $Paranoia, $LastAccess, $Avatar] = $Friend;
?>
<form class="manage_form" name="friends" action="friends.php" method="post">
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
    <table class="friends_table vertical_margin">
        <tr class="colhead">
            <td colspan="<?=($user->showAvatars() ? 3 : 2)?>">
                <span style="float: left;"><?=Users::format_username($FriendID, true, true, true, true)?>
<?php if (check_paranoia('ratio', $Paranoia, $Class, $FriendID)) { ?>
                &nbsp;Ratio: <strong><?=Format::get_ratio_html($Uploaded, $Downloaded)?></strong>
<?php
    }
    if (check_paranoia('uploaded', $Paranoia, $Class, $FriendID)) {
?>
                &nbsp;Up: <strong><?=Format::get_size($Uploaded)?></strong>
<?php
    }
    if (check_paranoia('downloaded', $Paranoia, $Class, $FriendID)) {
?>
                &nbsp;Down: <strong><?=Format::get_size($Downloaded)?></strong>
<?php } ?>
                </span>
<?php if (check_paranoia('lastseen', $Paranoia, $Class, $FriendID)) { ?>
                <span style="float: right;"><?=time_diff($LastAccess)?></span>
<?php } ?>
            </td>
        </tr>
        <tr>
<?php if ($user->showAvatars()) { ?>
            <td class="col_avatar avatar" valign="top">
                <?= $userMan->avatarMarkup($user, new Gazelle\User($FriendID)) ?>
            </td>
<?php } ?>
            <td valign="top">
                <input type="hidden" name="friendid" value="<?=$FriendID?>" />
                <textarea name="comment" rows="4" cols="65"><?=$Comment?></textarea>
            </td>
            <td class="left" valign="top">
                <input type="submit" name="action" value="Update" /><br />
                <input type="submit" name="action" value="Remove friend" /><br />
                <input type="submit" name="action" value="Contact" /><br />
            </td>
        </tr>
    </table>
</form>
<?php } ?>
    </div>
    <div class="linkbox">
        <?= $Pages ?>
    </div>
</div>
<?php
View::show_footer();
