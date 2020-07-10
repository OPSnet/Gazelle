<?php

if (!check_perms('admin_donor_log')) {
    error(403);
}

define('DONATIONS_PER_PAGE', 50);
list($Page, $Limit) = Format::page_limit(DONATIONS_PER_PAGE);

$dateSearch = !empty($_GET['after_date']) && !empty($_GET['before_date']);

$cond = [];
$args = [];
if (!empty($_GET['username'])) {
    $cond[] = "m.Username LIKE concat('%', ?, '%')";
    $args[] = trim($_GET['username']);
}
if ($dateSearch) {
    $cond[] = "d.Time BETWEEN ? AND ?";
    $args[] = trim($_GET['after_date']);
    $args[] = trim($_GET['before_date']);
}

$from = "FROM donations AS d INNER JOIN users_main AS m ON (m.ID = d.UserID)";
if ($cond) {
    $from .= " WHERE " . implode(' AND ', $cond);
}

$Results = $DB->scalar("SELECT count(*) $from", ...$args);

$args[] = $Limit;
$DB->prepared_query("
    SELECT
        d.UserID,
        d.Amount,
        d.Currency,
        d.xbt,
        d.Time,
        d.Source,
        m.Username,
        d.AddedBy,
        d.Reason
    $from
        ORDER BY d.Time DESC
        LIMIT ?
    ", ...$args
 );
$Donations = $DB->to_array();

$Total = $DB->scalar("
    SELECT SUM(Amount) FROM donations
");

$DB->prepared_query("
    SELECT date_format(Time,'%b %Y') AS Month,
        sum(xbt) as Amount
    FROM donations
    GROUP BY Month
    ORDER BY Time DESC
    LIMIT 0, 17
");
$Timeline = array_reverse($DB->to_array(false, MYSQLI_ASSOC, false));

View::show_header('Donation log');
?>
<script src="<?= STATIC_SERVER ?>functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>functions/highcharts_custom.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
Highcharts.chart('donation-timeline', {
    chart: {
        type: 'column',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'Donations Timeline',
        style: { color: '#c0c0c0', },
    },
    credits: { enabled: false },
    tooltip: {
        headerFormat: '<b>{point.x}</b><br/>',
        pointFormat: '{series.name}: {point.y}'
    },
    yAxis: {
        title: {text: 'bitcoin'},
    },
    xAxis: {
        categories: [<?= implode(',', array_map(function ($x) { return "'" . $x['Month'] . "'"; }, $Timeline)) ?>],
    },
    series: [
        { name: 'Donated',  data: [<?= implode(',', array_map(function ($x) { return  $x['Amount']; }, $Timeline)) ?>] }
    ]

})});
</script>
<div class="thin">
<div class="box pad">
    <figure class="highcharts-figure"><div id="donation-timeline"></div></figure>
</div>
<br />

<div>
    <form class="search_form" name="donation_log" action="" method="get">
        <input type="hidden" name="action" value="donation_log" />
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr>
                <td class="label"><strong>Username:</strong></td>
                <td>
                    <input type="search" name="username" size="60" value="<?php if (!empty($_GET['username'])) { echo display_str($_GET['username']); } ?>" />
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Date Range:</strong></td>
                <td>
                    <input type="date" name="after_date" />
                    <input type="date" name="before_date" max="<?= date('Y-m-d') ?>" />
                </td>
            </tr>
            <tr>
                <td class="label">
                    <input type="submit" value="Search donation log" />
                </td>
                <td>&nbsp;</td>
            </tr>
        </table>
    </form>
</div>
<br />
<div class="linkbox">
<?php
    $Pages = Format::get_pages($Page, $Results, DONATIONS_PER_PAGE, 11);
    echo $Pages;
?>
</div>
<table width="100%">
    <tr class="colhead">
        <td>User</td>
        <td>Fiat Amount</td>
        <td>Currency</td>
        <td>Bitcoin</td>
        <td>Source</td>
        <td>Reason</td>
        <td>Time</td>
    </tr>
<?php
    $PageTotal = 0;
    foreach ($Donations as $Donation) {
        $PageTotal += $Donation['Amount']; ?>
        <tr>
            <td><?=Users::format_username($Donation['UserID'], true)?> (<?=Users::format_username($Donation['AddedBy'])?>)</td>
            <td><?=display_str($Donation['Amount'])?></td>
            <td><?=display_str($Donation['Currency'])?></td>
            <td><?=display_str($Donation['xbt'])?></td>
            <td><?=display_str($Donation['Source'])?></td>
            <td><?=display_str($Donation['Reason'])?></td>
            <td><?=time_diff($Donation['Time'])?></td>
        </tr>
<?php
    } ?>
<tr class="colhead">
    <td>Page Total</td>
    <td><?=$PageTotal?></td>
    <td>Total</td>
    <td colspan="3"><?=$Total?></td>
</tr>
</table>
<div class="linkbox">
    <?=$Pages?>
</div>
<?php
View::show_footer();
