<?php
if (!check_perms('admin_login_watch')) {
    error(403);
}

if (isset($_POST['submit']) && isset($_POST['id']) && $_POST['submit'] == 'Unban' && is_number($_POST['id'])) {
    authorize();
    $DB->prepared_query("
        DELETE FROM login_attempts
        WHERE ID = ?
        ", (int)$_POST['id']
    );
}

View::show_header('Login Watch');

$DB->prepared_query('
    SELECT
        ID,
        IP,
        UserID,
        LastAttempt,
        Attempts,
        BannedUntil,
        Bans
    FROM login_attempts
    WHERE (BannedUntil > now() OR LastAttempt > now() - INTERVAL 6 HOUR)
    ORDER BY BannedUntil ASC');
?>
<div class="thin">
    <div class="header">
        <h2>Login Watch Management</h2>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>IP</td>
            <td>User</td>
            <td>Bans</td>
            <td>Remaining</td>
            <td>Submit</td>
<?php
if (check_perms('admin_manage_ipbans')) { ?>
            <td>Submit</td>
<?php
} ?>
        </tr>
<?php
$Row = 'b';
while (list($ID, $IP, $UserID, $LastAttempt, $Attempts, $BannedUntil, $Bans) = $DB->next_record()) {
    $Row = $Row === 'a' ? 'b' : 'a';
?>
        <tr class="row<?=$Row?>">
            <td>
                <?=$IP?>
            </td>
            <td>
                <?php if ($UserID != 0) { echo Users::format_username($UserID, true, true, true, true); } ?>
            </td>
            <td>
                <?=$Bans?>
            </td>
            <td>
                <?=time_diff($BannedUntil)?>
            </td>
            <td>
                <form class="manage_form" name="bans" action="" method="post">
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$ID?>" />
                    <input type="hidden" name="action" value="login_watch" />
                    <input type="submit" name="submit" value="Unban" />
                </form>
            </td>
<?php
if (check_perms('admin_manage_ipbans')) { ?>
            <td>
                <form class="manage_form" name="bans" action="" method="post">
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$ID?>" />
                    <input type="hidden" name="action" value="ip_ban" />
                    <input type="hidden" name="start" value="<?=$IP?>" />
                    <input type="hidden" name="end" value="<?=$IP?>" />
                    <input type="hidden" name="notes" value="Banned per <?=$Bans?> bans on login watch." />
                    <input type="submit" name="submit" value="IP Ban" />
                </form>
            </td>
<?php
} ?>
        </tr>
<?php
}
?>
    </table>
</div>
<?php
View::show_footer();
