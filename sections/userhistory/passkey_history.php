<?php
/************************************************************************
||------------|| User passkey history page ||--------------------------||

This page lists previous passkeys a user has used on the site. It gets
called if $_GET['action'] == 'passkey'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

if (!check_perms('users_view_keys', $Class)) {
    error(403);
}

$UserID = (int)$_GET['userid'];
if (!$UserID) {
    error(404);
}
$Username = Users::user_info($UserID)['Username'];

$DB->prepared_query("
    SELECT
        OldPassKey,
        NewPassKey,
        ChangeTime,
        ChangerIP
    FROM users_history_passkeys
    WHERE UserID = ?
    ORDER BY ChangeTime DESC
    ", $UserID
);

View::show_header("Passkey history for $Username");
?>
<div class="header">
    <h2><a href="/user.php?id=<?= $UserID ?>"><?= $Username ?></a> &rsaquo; Passkey History</h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Old</td>
        <td>New</td>
        <td>Changed</td>
        <td>IP <a href="/userhistory.php?action=ips&amp;userid=<?=$UserID?>" class="brackets">H</a></td>
    </tr>
<?php while ([$OldPassKey, $NewPassKey, $ChangeTime, $ChangerIP] = $DB->next_record()) { ?>
    <tr class="rowa">
        <td><?=display_str($OldPassKey)?></td>
        <td><?=display_str($NewPassKey)?></td>
        <td><?=time_diff($ChangeTime)?></td>
        <td><?=display_str($ChangerIP)?> <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($ChangerIP)?>" class="brackets tooltip" title="Search">S</a><br /><?=display_str(Tools::get_host_by_ip($ChangerIP))?></td>
    </tr>
<?php } ?>
</table>
<?php
View::show_footer();
