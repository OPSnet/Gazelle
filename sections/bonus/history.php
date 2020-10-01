<?php

View::show_header('Bonus Points Purchase History', 'bonus');

if (isset($_GET['userid']) && is_number($_GET['userid'])) {
    if (!check_perms('admin_bp_history')) {
        error(403);
    }
    $ID = (int)$_GET['userid'];
    $Header = 'Bonus Points Spending History for ' . Users::format_username($ID);
    $WhoSpent = Users::format_username($ID) . ' has spent';
}
else {
    $ID = G::$LoggedUser['ID'];
    $Header = 'Bonus Points Spending History';
    $WhoSpent = 'You have spent';
}

$Summary = $Bonus->getUserSummary($ID);

$Page  = max(1, isset($_GET['page']) ? intval($_GET['page']) : 1);
$Pages = Format::get_pages($Page, $Summary['nr'], TORRENTS_PER_PAGE);

if ($Summary['nr'] > 0) {
    $History = $Bonus->getUserHistory($ID, $Page, TORRENTS_PER_PAGE);
}

?>
<div class="header">
    <h2><?= $Header ?></h2>
</div>
<div class="linkbox">
    <a href="wiki.php?action=article&name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php" class="brackets">Bonus Point Shop</a>
    <a href="bonus.php?action=bprates<?= check_perms('admin_bp_history') && $ID != G::$LoggedUser['ID'] ? "&userid=$ID" : '' ?>" class="brackets">Bonus Point Rates</a>
</div>

<div class="thin">
<?php
$has_spent = 0;
$PoolSummary = $Bonus->getUserPoolHistory($ID);
$pool_total = 0;
if (!is_null($PoolSummary)) {
    $has_spent++;
    foreach ($PoolSummary as $p) {
        $when = (time() < strtotime($p['until_date']))
            ? " ending in " . time_diff($p['until_date'])
            : " ended " . time_diff($p['until_date']) . ' ago';
        $pool_total += $p['total'];
?>
    <h4><?= $WhoSpent ?> <?=number_format($p['total']) ?> bonus points to donate to the <?= $p['name'] . $when ?>.</h4>
<?php
    }
}
if ($Summary['total']) {
    $also = ($has_spent) ? 'a further ' : '';
    $has_spent++;
?>
    <h4><?= "$WhoSpent $also" . number_format($Summary['total']) ?> bonus points to purchase <?= $Summary['nr'] ?> <?= $Summary['nr'] == 1 ? 'item' : 'items' ?>.</h4>
<?php
}
if ($has_spent == 2) {
    $total = $pool_total + $Summary['total'];
    if ($total > 500000) { $adj = 'very '; }
    elseif ($total >  1000000) { $adj = 'very, very '; }
    elseif ($total >  5000000) { $adj = 'extremely '; }
    elseif ($total > 10000000) { $adj = 'exceptionally '; }
    else { $adj = ''; }
?>
    <h4>That makes a grand total of <?= number_format($total) ?> points, <?= $adj ?>well done!</h4>
<?php
} elseif (!$has_spent) {
?>
    <h3>No purchase history.</h3>
<?php
}
if (isset($History)) {
?>
    <div class="linkbox">
        <?= $Pages ?>
    </div>
    <table>
        <thead>
            <tr class="colhead">
                <td>Item</td>
                <td width="50px" align="right">Price</td>
                <td width="150px">Purchase Date</td>
                <td>For</td>
            </tr>
        </thead>
        <tbody>
<?php
    foreach ($History as $Item) { ?>
            <tr>
                <td><?= $Item['Title'] ?></td>
                <td align="right"><?= number_format($Item['Price']) ?></td>
                <td><?= time_diff($Item['PurchaseDate']) ?></td>
                <td><?= !$Item['OtherUserID'] ? '&nbsp;' : Users::format_username($Item['OtherUserID']) ?></td>
            </tr>
<?php
    } ?>
        </tbody>
    </table>
<?php
} ?>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php

View::show_footer();
