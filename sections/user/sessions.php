<?php

//TODO: restrict to viewing below class, username in h2
if (isset($_GET['userid']) && check_perms('users_view_ips') && check_perms('users_logout')) {
    if (!is_number($_GET['userid'])) {
        error(404);
    }
    $UserID = $_GET['userid'];
} else {
    $UserID = G::$LoggedUser['ID'];
}

if (isset($_POST['all'])) {
    authorize();

    $DB->query("
        DELETE FROM users_sessions
        WHERE UserID = '$UserID'
            AND SessionID != '$SessionID'");
    $Cache->delete_value("users_sessions_$UserID");
}

if (isset($_POST['session'])) {
    authorize();

    $DB->query("
        DELETE FROM users_sessions
        WHERE UserID = '$UserID'
            AND SessionID = '".db_string($_POST['session'])."'");
    $Cache->delete_value("users_sessions_$UserID");
}

$UserSessions = $Cache->get_value('users_sessions_'.$UserID);
if (!is_array($UserSessions)) {
    $DB->query("
        SELECT
            SessionID,
            Browser,
            OperatingSystem,
            IP,
            LastUpdate
        FROM users_sessions
        WHERE UserID = '$UserID'
        ORDER BY LastUpdate DESC");
    $UserSessions = $DB->to_array('SessionID', MYSQLI_ASSOC);
    $Cache->cache_value("users_sessions_$UserID", $UserSessions, 0);
}

list($UserID, $Username) = array_values(Users::user_info($UserID));
View::show_header($Username.' &rsaquo; Sessions');
?>
<div class="thin">
<h2><?=Users::format_username($UserID, $Username)?> &rsaquo; Sessions</h2>
    <div class="box pad">
        <p>Note: Clearing cookies can result in ghost sessions which are automatically removed after 30 days.</p>
    </div>
    <div class="box pad">
        <table cellpadding="5" cellspacing="1" border="0" class="session_table border" width="100%">
            <tr class="colhead">
                <td class="nobr"><strong>IP address</strong></td>
                <td><strong>Browser</strong></td>
                <td><strong>Platform</strong></td>
                <td class="nobr"><strong>Last activity</strong></td>
                <td>
                    <form class="manage_form" name="sessions" action="" method="post">
                        <input type="hidden" name="action" value="sessions" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="all" value="1" />
                        <input type="submit" value="Log out all" />
                    </form>
                </td>
            </tr>
<?php
    $Row = 'a';
    foreach ($UserSessions as $Session) {
        //list($ThisSessionID, $Browser, $OperatingSystem, $IP, $LastUpdate) = array_values($Session);
        $Row = $Row === 'a' ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td class="nobr"><?=$Session['IP']?></td>
                <td><?=$Session['Browser']?></td>
                <td><?=$Session['OperatingSystem']?></td>
                <td><?=time_diff($Session['LastUpdate'])?></td>
                <td>
                    <form class="delete_form" name="session" action="" method="post">
                        <input type="hidden" name="action" value="sessions" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="session" value="<?=$Session['SessionID']?>" />
                        <input type="submit" value="<?=(($Session['SessionID'] === $SessionID) ? 'Current" disabled="disabled' : 'Log out') ?>" />
                    </form>
                </td>
            </tr>
<?php
    } ?>
        </table>
    </div>
</div>
<?php

View::show_footer();
?>
