<?php
/************************************************************************
||------------|| User email history page ||---------------------------||

This page lists previous email addresses a user has used on the site. It
gets called if $_GET['action'] == 'email'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/


$UserID = $_GET['userid'];
if (!is_number($UserID)) {
    error(404);
}

list($Username, $Joined, $Class) = $DB->row("
    SELECT um.Username, ui.JoinDate, p.Level AS Class
    FROM users_main        um
    INNER JOIN users_info  ui ON (ui.UserID = um.ID)
    INNER JOIN permissions p  ON (p.ID = um.PermissionID)
    WHERE um.ID = ?
    ", $UserID
);

if (!check_perms('users_view_email', $Class)) {
    error(403);
}

$UsersOnly = isset($_GET['usersonly']);

View::show_header("Email history for $Username");

if ($UsersOnly) {
    $DB->query("
        SELECT
            u.Email,
            now() AS Time,
            u.IP,
            c.Code
        FROM users_main AS u
            LEFT JOIN users_main AS u2 ON u2.Email = u.Email AND u2.ID != '$UserID'
            LEFT JOIN geoip_country AS c ON INET_ATON(u.IP) BETWEEN c.StartIP AND c.EndIP
        WHERE u.ID = '$UserID'
            AND u2.ID > 0
        UNION
        SELECT
            h.Email,
            h.Time,
            h.IP,
            c.Code
        FROM users_history_emails AS h
            LEFT JOIN users_history_emails AS h2 ON h2.email = h.email and h2.UserID != '$UserID'
            LEFT JOIN geoip_country AS c ON INET_ATON(h.IP) BETWEEN c.StartIP AND c.EndIP
        WHERE h.UserID = '$UserID'
            AND h2.UserID > 0
        ORDER BY Time DESC");
} else {
    $DB->query("
        SELECT
            u.Email,
            now() AS Time,
            u.IP,
            c.Code
        FROM users_main AS u
            LEFT JOIN geoip_country AS c ON INET_ATON(u.IP) BETWEEN c.StartIP AND c.EndIP
        WHERE u.ID = '$UserID'
        UNION
        SELECT
            h.Email,
            h.Time,
            h.IP,
            c.Code
        FROM users_history_emails AS h
            LEFT JOIN geoip_country AS c ON INET_ATON(h.IP) BETWEEN c.StartIP AND c.EndIP
        WHERE UserID = '$UserID'
        ORDER BY Time DESC");
}
$History = $DB->to_array();
?>
<div class="header">
    <h2>Email history for <a href="user.php?id=<?=$UserID ?>"><?=$Username ?></a></h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Email</td>
        <td>Set</td>
        <td>IP <a href="userhistory.php?action=ips&amp;userid=<?=$UserID ?>" class="brackets">H</a></td>
<?php if ($UsersOnly) {
?>
    <td>User</td>
<?php
}
?>
    </tr>
<?php
foreach ($History as $Key => $Values) {
    if (isset($History[$Key + 1])) {
        $Values['Time'] = $History[$Key + 1]['Time'];
    } else {
        $Values['Time'] = $Joined;
    }
?>
    <tr class="rowa">
        <td><?=display_str($Values['Email'])?></td>
        <td><?=time_diff($Values['Time'])?></td>
        <td><?=display_str($Values['IP'])?> (<?=display_str($Values['Code'])?>) <a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($Values['IP'])?>" class="brackets tooltip" title="Search">S</a></td>
<?php
    if ($UsersOnly) {
        $ueQuery = $DB->query("
                    SELECT
                        ue.UserID,
                        um.Username,
                        ue.Time,
                        ue.IP
                    FROM users_history_emails AS ue, users_main AS um
                    WHERE ue.Email = '".db_string($Values['Email'])."'
                        AND ue.UserID != $UserID
                        AND um.ID = ue.UserID");
        while (list($UserID2, $Time, $IP) = $DB->next_record()) { ?>
    </tr>
    <tr>
        <td></td>
        <td><?=time_diff($Time)?></td>
        <td><?=display_str($IP)?></td>
<?php
            $UserURL = site_url()."user.php?id=$UserID2";
            $DB->query("
                SELECT Enabled
                FROM users_main
                WHERE ID = $UserID2");
            list($Enabled) = $DB->next_record();
            $DB->set_query_id($ueQuery);
?>
        <td><a href="<?=display_str($UserURL)?>"><?=Users::format_username($UserID2, false, false, true)?></a></td>
    </tr>
<?php
        }
    }
} ?>
</table>
<?php

View::show_footer();
