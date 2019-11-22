<?php
if (!check_perms('users_view_invites')) {
    error(403);
}
$Title = 'Invite Pool';
View::show_header($Title);
define('INVITES_PER_PAGE', 50);
list($Page, $Limit) = Format::page_limit(INVITES_PER_PAGE);

if (!empty($_POST['invitekey']) && check_perms('users_edit_invites')) {
    authorize();
    $DB->prepared_query('
        DELETE FROM invites
        WHERE InviteKey = ?
        ', trim($_POST['invitekey'])
    );
}

$sql = "
    SELECT
        SQL_CALC_FOUND_ROWS
        um.ID,
        um.IP,
        i.InviteKey,
        i.Expires,
        i.Email
    FROM invites AS i
    JOIN users_main AS um ON (um.ID = i.InviterID)
";


if (empty($_GET['search'])) {
    $args = [];
}
else {
    $sql .= " WHERE i.Email LIKE concat('%', ?, '%')";
    $args[] = trim($_GET['search']);
}

if (strpos($Limit, ',') !== false) {
    list($lim, $off) = explode(',', $Limit);
    $sql .= ' ORDER BY i.Expires DESC LIMIT ? OFFSET ?';
    $args[] = trim($lim);
    $args[] = trim($off);
} else {
    $sql .= ' ORDER BY i.Expires DESC LIMIT ?';
    $args[] = $Limit;
}
$RS = $DB->prepared_query($sql, ...$args);

$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();

$DB->set_query_id($RS);
?>
    <div class="header">
        <h2><?=$Title?></h2>
    </div>
    <div class="box pad">
        <p><?=number_format($Results)?> unused invites have been sent.</p>
    </div>
    <br />
    <div>
        <form class="search_form" name="invites" action="" method="get">
            <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
                <tr>
                    <td class="label"><strong>Email address:</strong></td>
                    <td>
                        <input type="hidden" name="action" value="invite_pool" />
                        <input type="email" name="search" size="60" value="<?=display_str($Search)?>" />
                        &nbsp;
                        <input type="submit" value="Search log" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <div class="linkbox">
<?php
    $Pages = Format::get_pages($Page, $Results, INVITES_PER_PAGE, 11) ;
    echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>Inviter</td>
            <td>Email address</td>
            <td>IP address</td>
            <td>Invite link</td>
            <td>Expires</td>
<?php if (check_perms('users_edit_invites')) { ?>
            <td>Controls</td>
<?php } ?>
        </tr>
<?php
    $Row = 'b';
    while (list($UserID, $IP, $InviteKey, $Expires, $Email) = $DB->next_record()) {
        $Row = $Row === 'b' ? 'a' : 'b';
?>
        <tr class="row<?=$Row?>">
            <td><?=Users::format_username($UserID, true, true, true, true)?></td>
            <td><?=display_str($Email)?></td>
            <td><?=Tools::display_ip($IP)?></td>
            <td><a href="register.php?invite=<?= $InviteKey ?>"><?= $InviteKey ?></a></td>
            <td><?=time_diff($Expires)?></td>
<?php   if (check_perms('users_edit_invites')) { ?>
            <td>
                <form class="delete_form" name="invite" action="" method="post">
                    <input type="hidden" name="action" value="invite_pool" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="invitekey" value="<?=display_str($InviteKey)?>" />
                    <input type="submit" value="Delete" />
                </form>
            </td>
<?php   } ?>
        </tr>
<?php } ?>
    </table>
<?php if ($Pages) { ?>
    <div class="linkbox pager"><?=($Pages)?></div>
<?php
}
View::show_footer();
