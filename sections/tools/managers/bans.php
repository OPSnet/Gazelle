<?php

if (!check_perms('admin_manage_ipbans')) {
    error(403);
}
$IPv4Man = new \Gazelle\Manager\IPv4;

if (isset($_POST['submit'])) {
    authorize();
    if ($_POST['submit'] == 'Delete') { //Delete
        $id = (int)$_POST['id'];
        if ($id < 1) {
            error(0);
        }
        $IPv4Man->removeBan($id);
    } else { //Edit & Create, Shared Validation
        $Val = new Gazelle\Util\Validator;
        $Val->setFields([
            ['start', '1','regex','You must include the starting IP address.',['regex'=>'/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i']],
            ['end', '1','regex','You must include the ending IP address.',['regex'=>'/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i']],
            ['notes', '1','string','You must include the reason for the ban.'],
        ]);
        if (!$Val->validate($_POST)) {
            error($Val->errorMessage());
        }
        $IPv4Man->createBan($LoggedUser['ID'], $_POST['start'], $_POST['end'], trim($_POST['notes']));
    }
}

define('BANS_PER_PAGE', '50');

$header = new \Gazelle\Util\SortableTableHeader('created', [
    'fromip'     => ['dbColumn' => 'i.FromIP',    'defaultSort' => 'asc',  'text' => 'From'],
    'toip'       => ['dbColumn' => 'i.ToIP',      'defaultSort' => 'asc',  'text' => 'To'],
    'reason'     => ['dbColumn' => 'i.Reason',    'defaultSort' => 'asc',  'text' => 'Reason'],
    'username'   => ['dbColumn' => 'um.Username', 'defaultSort' => 'asc',  'text' => 'Added By'],
    'created'    => ['dbColumn' => 'i.created',   'defaultSort' => 'desc', 'text' => 'Date'],
]);
$OrderBy = $header->getOrderBy();
$OrderDir = $header->getOrderDir();

$cond = [];
$args = [];
if (!empty($_REQUEST['notes'])) {
    $cond[] = "i.Reason LIKE concat('%', ?, '%')";
    $args[] = $_REQUEST['notes'];
}
if (!empty($_REQUEST['ip']) && preg_match('/'.IP_REGEX.'/', $_REQUEST['ip'])) {
    $cond[] = "? BETWEEN i.FromIP AND i.ToIP";
    $args[] = $IPv4Man->ip2ulong($_REQUEST['ip']);
}
$from = "FROM ip_bans i LEFT JOIN users_main um ON (um.ID = i.user_id)" . (count($cond) ? (' WHERE ' . implode(' AND ', $cond)) : '');

$Results = $DB->scalar("SELECT count(*) $from", ...$args);
[$Page, $Limit] = Format::page_limit(BANS_PER_PAGE);
$PageLinks = Format::get_pages($Page, $Results, BANS_PER_PAGE, 11);

$from .= " ORDER BY $OrderBy $OrderDir LIMIT " . $Limit;
$DB->prepared_query("SELECT i.ID, i.FromIP, i.ToIP, i.Reason, i.user_id, i.created, um.Username $from", ...$args);
$banQ = $DB->get_query_id();

View::show_header('IP Address Bans');
?>
<div class="header">
    <h2>IP Address Bans</h2>
</div>
<div class="linkbox">
    <a href="tools.php?action=login_watch">Login Watch</a>
</div>
<div>
    <form class="search_form" name="bans" action="" method="get">
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr>
                <td class="label"><label for="ip">IP address:</label></td>
                <td>
                    <input type="hidden" name="action" value="ip_ban" />
                    <input type="search" id="ip" name="ip" size="20" value="<?=(!empty($_GET['ip']) ? display_str($_GET['ip']) : '')?>" />
                </td>
                <td class="label"><label for="notes">Notes:</label></td>
                <td>
                    <input type="hidden" name="action" value="ip_ban" />
                    <input type="search" id="notes" name="notes" size="60" value="<?=(!empty($_GET['notes']) ? display_str($_GET['notes']) : '')?>" />
                </td>
                <td>
                    <input type="submit" value="Search" />
                </td>
            </tr>
        </table>
    </form>
</div>
<br />

<h3>Manage</h3>
<div class="linkbox">
<?=$PageLinks?>
</div>
<?php

?>
<table width="100%">
    <tr class="colhead">
        <td title="The IP addresses specified are &#42;inclusive&#42;. The left box is the beginning of the IP address range, and the right box is the end of the IP address range.">
            <?= $header->emit('fromip') ?>
        </td>
        <td><?= $header->emit('toip') ?></td>
        <td><?= $header->emit('reason') ?></td>
        <td><?= $header->emit('username') ?></td>
        <td><?= $header->emit('created') ?></td>
    </tr>
    <tr class="rowa">
        <form class="create_form" name="ban" action="" method="post">
            <input type="hidden" name="action" value="ip_ban" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td colspan="2">
                <input type="text" size="12" name="start" />
                <input type="text" size="12" name="end" />
            </td>
            <td>
                <input type="text" size="72" name="notes" />
            </td>
            <td colspan="2">&nbsp;</td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
<?php

$DB->set_query_id($banQ);
$Row = 'b';
while ([$ID, $Start, $End, $Reason, $userId, $created, $username] = $DB->next_record()) {
    $Row = $Row === 'a' ? 'b' : 'a';
    $Start = long2ip($Start);
    $End = long2ip($End);
?>
    <tr class="row<?=$Row?>">
        <form class="manage_form" name="ban" action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="ip_ban" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td colspan="2">
                <input type="text" size="12" name="start" value="<?=$Start?>" />
                <input type="text" size="12" name="end" value="<?=$End?>" />
            </td>
            <td>
                <input type="text" size="72" name="notes" value="<?=$Reason?>" />
            </td>
            <td><?= Users::format_username($userId) ?></td>
            <td><?= time_diff($created) ?></td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" />
            </td>
        </form>
    </tr>
<?php
    $DB->set_query_id($banQ);
}
?>
</table>
<div class="linkbox">
<?= $PageLinks ?>
</div>
<?php
View::show_footer();
