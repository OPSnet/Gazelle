<?php
if (!check_perms('users_view_ips') || !check_perms('users_view_email')) {
    error(403);
}

$afterDate = $_POST['after_date'] ?? null;
$beforeDate = $_POST['before_date'] ?? null;

$cond = [];
$args = [];
if (is_null($afterDate) || is_null($beforeDate)) {
    $cond[] = 'i.JoinDate > now() - INTERVAL 3 DAY';
} else {
    [$Y, $M, $D] = explode('-', $afterDate);
    if (!checkdate($M, $D, $Y)) {
        error('Incorrect "after" date format');
    }
    $args[] = $afterDate;
    [$Y, $M, $D] = explode('-', $beforeDate);
    if (!checkdate($M, $D, $Y)) {
        error('Incorrect "before" date format');
    }
    $args[] = $beforeDate;
    $cond[] = 'i.JoinDate BETWEEN ? AND ?';
}

$whereList = implode(' AND ', $cond);
$Results = $DB->scalar("
    SELECT count(*)
    FROM users_info AS i
    WHERE $whereList
    ", ...$args
);

[$Page, $Limit] = Format::page_limit(USERS_PER_PAGE);
$Pages = Format::get_pages($Page, $Results, USERS_PER_PAGE, 11);

$DB->prepared_query("
    SELECT um.ID,
        um.IP,
        um.ipcc,
        um.Email,
        um.Username,
        um.PermissionID,
        uls.Uploaded,
        uls.Downloaded,
        um.Enabled,
        i.Donor,
        i.Warned,
        i.JoinDate,
        (
            SELECT COUNT(h1.UserID)
            FROM users_history_ips AS h1
            WHERE h1.IP = um.IP
        ) AS Uses,
        im.ID,
        im.IP,
        im.ipcc,
        im.Email,
        im.Username,
        im.PermissionID,
        ils.Uploaded,
        ils.Downloaded,
        im.Enabled,
        ii.Donor,
        ii.Warned,
        ii.JoinDate,
        (
            SELECT COUNT(h2.UserID)
            FROM users_history_ips AS h2
            WHERE h2.IP = im.IP
        ) AS InviterUses
    FROM users_main AS um
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
    INNER JOIN users_info AS i ON (i.UserID = um.ID)
    LEFT JOIN users_main AS im ON (i.Inviter = im.ID)
    LEFT JOIN users_leech_stats AS ils ON (ils.UserID = im.ID)
    LEFT JOIN users_info AS ii ON (i.Inviter = ii.UserID)
    WHERE $whereList
    ORDER BY i.Joindate DESC
    LIMIT $Limit
    ", ...$args
);

View::show_header('Registration log');
?>

<form action="" method="post" acclass="thin box pad">
    <input type="hidden" name="action" value="registration_log" />
    Joined after: <input type="date" name="after_date" />
    Joined before: <input type="date" name="before_date" />
    <input type="submit" value="Search" />
</form>

<?php if (!$DB->has_results()) { ?>
    <h2 align="center">There have been no new registrations in the past 72 hours.</h2>
<?php } else { ?>
    <div class="linkbox">
        <?= $Pages; ?>
    </div>

    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>Ratio</td>
            <td>Email</td>
            <td>IP address</td>
            <td>Country</td>
            <td>Host</td>
            <td>Registered</td>
        </tr>
<?php
    while ([$UserID, $IP, $IPCC, $Email, $Username, $PermissionID, $Uploaded, $Downloaded, $Enabled, $Donor, $Warned, $Joined, $Uses, $InviterID, $InviterIP, $InviterIPCC, $InviterEmail, $InviterUsername, $InviterPermissionID, $InviterUploaded, $InviterDownloaded, $InviterEnabled, $InviterDonor, $InviterWarned, $InviterJoined, $InviterUses] = $DB->next_record()) {
    $Row = $IP === $InviterIP ? 'a' : 'b';
?>
        <tr class="row<?=$Row?>">
            <td><?=Users::format_username($UserID, true, true, true, true)?><br /><?=Users::format_username($InviterID, true, true, true, true)?></td>
            <td><?=Format::get_ratio_html($Uploaded, $Downloaded)?><br /><?=Format::get_ratio_html($InviterUploaded, $InviterDownloaded)?></td>
            <td>
                <span style="float: left;"><?=display_str($Email)?></span>
                <span style="float: right;"><a href="userhistory.php?action=email&amp;userid=<?=$UserID?>" title="History" class="brackets tooltip">H</a> <a href="/user.php?action=search&amp;email_history=on&amp;email=<?=display_str($Email)?>" title="Search" class="brackets tooltip">S</a></span><br />
                <span style="float: left;"><?=display_str($InviterEmail)?></span>
                <span style="float: right;"><a href="userhistory.php?action=email&amp;userid=<?=$InviterID?>" title="History" class="brackets tooltip">H</a> <a href="/user.php?action=search&amp;email_history=on&amp;email=<?=display_str($InviterEmail)?>" title="Search" class="brackets tooltip">S</a></span><br />
            </td>
            <td>
                <span style="float: left;"><?=display_str($IP)?></span>
                <span style="float: right;"><?=display_str($Uses)?> <a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>" title="History" class="brackets tooltip">H</a> <a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="Search" class="brackets tooltip">S</a> <a href="http://whatismyipaddress.com/ip/<?=display_str($IP)?>" title="WI" class="brackets tooltip">WI</a></span><br />
                <span style="float: left;"><?=display_str($InviterIP)?></span>
                <span style="float: right;"><?=display_str($InviterUses)?> <a href="userhistory.php?action=ips&amp;userid=<?=$InviterID?>" title="History" class="brackets tooltip">H</a> <a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($InviterIP)?>" title="Search" class="brackets tooltip">S</a> <a href="http://whatismyipaddress.com/ip/<?=display_str($InviterIP)?>" title="WI" class="brackets tooltip">WI</a></span><br />
            </td>
            <td>
                <?=$IPCC?> <br />
                <?=$InviterIPCC?>
            </td>
            <td>
                <?=Tools::get_host_by_ajax($IP)?><br />
                <?=Tools::get_host_by_ajax($InviterIP)?>
            </td>
            <td>
                <?=time_diff($Joined)?><br />
                <?=time_diff($InviterJoined)?>
            </td>
        </tr>
<?php } ?>
    </table>
    <div class="linkbox">
        <?= $Pages ?>
    </div>
<?php
}
View::show_footer();
