<?php
/************************************************************************
This page lists previous IPs a user has connected to the site with. It
gets called if $_GET['action'] == 'ips'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

if (!check_perms('users_mod') || !check_perms('users_view_ips')) {
    error(403);
}

require_once(__DIR__ . '/../torrents/functions.php');

$userId = (int)$_GET['userid'] ?? null;
$ipAddr = $_GET['ip'] ?? null;

if (!($userId || preg_match('/^' . IP_REGEX . '$/', $ipAddr))) {
    error(404);
}

$Username = Users::user_info($userId)['Username'];
View::show_header('Tracker IP address history for ' . ($userId ? $Username : "IP address $ipAddr"));
?>
<script type="text/javascript">
function ShowIPs(rowname) {
    $('tr[name="'+rowname+'"]').gtoggle();
}
</script>
<div class="thin">
    <div class="header">
<?php if ($userId) { ?>
        <h2><a href="user.php?id=<?= $userId ?>"><?= $Username ?></a> &rsaquo; Tracker IP address history</h2>
<?php } else { ?>
        <h2>Tracker history for IP address <?= $ipAddr ?></h2>
<?php } ?>
    </div>
<?php
$searchCol = is_null($ipAddr) ? 'uid' : 'IP';
$DB->prepared_query("
    SELECT IP,
        count(*) AS total,
        from_unixtime(min(tstamp)) AS first,
        from_unixtime(max(tstamp)) AS last
    FROM xbt_snatched
    WHERE $searchCol = ?
    GROUP BY IP
    ORDER BY max(tstamp) DESC
    ", is_null($ipAddr) ? $userId : $ipAddr
);
$summary = $DB->to_array(MYSQLI_ASSOC);

if ($summary) {
    $urlStem = $_SERVER['SCRIPT_NAME'] . '?action=tracker_ips&amp;ip=';
?>
    <div class="header">
        <h3>Summary</h3>
    </div>
    <table>
        <tr class="colhead">
<?php if (check_perms('users_mod')) { ?>
            <td title="Click on an address to view list of users seen on that address">IP address</td>
<?php } else { ?>
            <td>IP address</td>
<?php } ?>
            <td>Total</td>
            <td>First Seen</td>
            <td>Last Seen</td>
        </tr>
<?php foreach ($summary as $s) { ?>
        <tr>
<?php if (check_perms('users_mod')) { ?>
            <td><a href="<?= $urlStem . $s['IP'] ?>"><?= $s['IP'] ?></a></td>
<?php } else { ?>
            <td>"><?= $s['IP'] ?></td>
<?php } ?>
            <td><?= $s['total'] ?></td>
            <td><?= time_diff($s['first']) ?></td>
            <td><?= time_diff($s['last']) ?></td>
<?php } ?>
    </table>
<?php
}

[$Page, $Limit] = Format::page_limit(IPS_PER_PAGE);
$column = $userId ? 'uid' : 'ip';

$NumResults = $DB->scalar("
    SELECT count(*) FROM xbt_snatched WHERE $column = ?
    ", $userId ?: $ipAddr
);
$Pages = Format::get_pages($Page, $NumResults, IPS_PER_PAGE, 9);

$DB->prepared_query("
    SELECT IP, fid, tstamp
    FROM xbt_snatched
    WHERE $column = ?
    ORDER BY tstamp DESC
    LIMIT ?
    ", $userId ?: $ipAddr, $Limit
);
$Results = $DB->to_array();
?>
    <div class="header">
        <h3>Detail</h3>
    </div>

    <div class="linkbox"><?= $Pages ?></div>
    <table>
        <tr class="colhead">
<?php if ($userId) { ?>
            <td>IP address</td>
<?php } else { ?>
            <td>Username</td>
<?php } ?>
            <td>Torrent</td>
            <td>Time</td>
        </tr>
<?php
foreach ($Results as $Index => $Result) {
    [$value, $torrentId, $Time] = $Result;
    [$torrentInfo] = get_torrent_info($torrentId, 0, true, true);
    $urlStem = $_SERVER['SCRIPT_NAME'] . '?action=tracker_ips&amp;userid=';
?>
    <tr class="rowa">
        <td>
<?php if ($userId) { ?>
            <?= $value ?> (<?=Tools::get_country_code_by_ajax($value) ?>)<br /><?= Tools::get_host_by_ajax($value) ?>
            <a href="http://whatismyipaddress.com/ip/<?= display_str($value) ?>" class="brackets tooltip" title="Search WIMIA.com">WI</a>
<?php } else { ?>
            <a href="<?= $urlStem . $value ?>"><?= Users::user_info($value)['Username'] ?></a>
<?php } ?>
        </td>
        <td><a href="torrents.php?torrentid=<?= $torrentId ?>"><?= $torrentInfo['Name'] ?></a></td>
        <td><?=date('Y-m-d g:i:s', $Time)?></td>
    </tr>
<?php } ?>
</table>
<div class="linkbox">
    <?= $Pages ?>
</div>
</div>

<?php
View::show_footer();
