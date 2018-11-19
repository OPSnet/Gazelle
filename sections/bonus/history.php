<?php

View::show_header('Bonus Points Purchase History', 'bonus');

if (check_perms('admin_bp_history') && isset($_GET['id']) && is_number($_GET['id'])) {
    $ID = (int)$_GET['id'];
    $Header = 'Bonus Points Spending History for ' . Users::format_username($ID);
    $WhoSpent = Users::format_username($ID) . ' has spent';
} else {
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
<? if ($Summary['total']) { ?>
    <h3><?=$WhoSpent ?> <?=number_format($Summary['total']) ?> bonus points to purchase <?=$Summary['nr'] ?> <?=$Summary['nr'] == 1 ? 'item' : 'items' ?>.</h3>
<? } else { ?>
    <h3>No purchase history</h3>
<?
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
<?	foreach ($History as $Item) { ?>
            <tr>
                <td><?= $Item['Title'] ?></td>
                <td align="right"><?= number_format($Item['Price']) ?></td>
                <td><?= time_diff($Item['PurchaseDate']) ?></td>
                <td><?= !$Item['OtherUserID'] ? '&nbsp;' : Users::format_username($Item['OtherUserID']) ?></td>
            </tr>
<?	} ?>
        </tbody>
    </table>
<? } ?>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?

View::show_footer();
