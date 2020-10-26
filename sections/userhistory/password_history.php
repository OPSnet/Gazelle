<?php
/************************************************************************
||------------|| Password reset history page ||------------------------||

This page lists password reset IP and Times a user has made on the site.
It gets called if $_GET['action'] == 'password'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

if (!check_perms('users_view_keys')) {
    error(403);
}

$UserID = (int)$_GET['userid'];
if (!$UserID) {
    error(404);
}
$Username = Users::user_info($UserID)['Username'];

$DB->prepared_query("
    SELECT
        ChangeTime,
        ChangerIP
    FROM users_history_passwords
    WHERE UserID = $UserID
    ORDER BY ChangeTime DESC
");

View::show_header("<?= $Username ?> &rsaquo; Password reset history");
?>
<div class="header">
    <h2><a href="/user.php?id=<?= $UserID ?>"><?= $Username ?></a> &rsaquo; Password reset history</h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Changed</td>
        <td>IP <a href="/userhistory.php?action=ips&amp;userid=<?=$UserID?>" class="brackets">H</a></td>
    </tr>
<?php while ([$ChangeTime, $ChangerIP] = $DB->next_record()) { ?>
    <tr class="rowa">
        <td><?=time_diff($ChangeTime)?></td>
        <td><?=display_str($ChangerIP)?> <a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($ChangerIP)?>" class="brackets tooltip" title="Search">S</a><br /><?=Tools::get_host_by_ajax($ChangerIP)?></td>
    </tr>
<?php } ?>
</table>
<?php
View::show_footer();
