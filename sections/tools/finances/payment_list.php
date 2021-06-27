<?php

if (!check_perms('admin_manage_payments') && !check_perms('admin_view_payments')) {
    error(403);
}
$donorMan = new Gazelle\Manager\Donation;

View::show_header('Payment Dates');
?>
<div class="header">
    <h2>Payment Dates</h2>
</div>
<table>
    <tr class="colhead">
        <td>Payment</td>
        <td>Expiry</td>
        <td>Annual Rent</td>
        <td>Currency Code</td>
        <td>Equivalent XBT</td>
        <td>Active</td>
<?php if (check_perms('admin_manage_payments')) { ?>
        <td>Submit</td>
<?php } ?>
    </tr>
<?php
$Row = 'b';
$totalRent = 0;

$Payment = new \Gazelle\Manager\Payment;
$paymentList = $Payment->list();

foreach ($paymentList as $r) {
    if ($r['Active']) {
        $totalRent += $r['btcRent'];
    }
    $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?= $Row ?>">
<?php if (!check_perms('admin_manage_payments')) { ?>
            <td><?= $r['Text'] ?></td>
            <td><?= date('Y-m-d', strtotime($r['Expiry'])) ?></td>
            <td><?= $r['Rent'] ?></td>
            <td><?= $r['cc'] ?></td>
            <td title="Based on a rate of <?= sprintf('%0.4f', $r['fiatRate'] )?>"><?= $r['btcRent'] ?></td>
            <td><?= $r['Active'] == '1' ? 'Active' : 'Inactive' ?></td>
<?php } else { ?>
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="action" value="payment_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="id" value="<?= $r['ID'] ?>" />
            <td>
                <input type="text" name="text" value="<?= $r['Text'] ?>" />
            </td>
            <td>
                <input type="text" name="expiry" value="<?=date('Y-m-d', strtotime($r['Expiry']))?>" placeholder="YYYY-MM-DD" />
            </td>
            <td>
                <input type="text" name="rent" value="<?= $r['Rent'] ?>" />
            </td>
            <td>
                <select name="cc">
                    <option value="XBT"<?= $r['cc'] == 'XBT' ? ' selected="selected"' : '' ?>>XBT</option>
                    <option value="EUR"<?= $r['cc'] == 'EUR' ? ' selected="selected"' : '' ?>>EUR</option>
                    <option value="USD"<?= $r['cc'] == 'USD' ? ' selected="selected"' : '' ?>>USD</option>
                </select>
            </td>
            <td title="Based on a rate of <?= sprintf('%0.4f', $r['fiatRate'] )?>"><?= $r['btcRent'] ?></td>
            <td>
                <input type="checkbox" name="active"<?=($r['Active'] == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this payment? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php
    } /* admin_manage_payments */
} /* foreach */

if (check_perms('admin_manage_payments')) {
?>
    <tr class="colhead">
        <td colspan="7">Create Payment</td>
    </tr>
    <tr class="rowa">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="action" value="payment_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="15" name="text" value="" />
            </td>
            <td>
                <input type="text" size="10" name="expiry" value="" placeholder="YYYY-MM-DD" />
            </td>
            <td>
                <input type="text" name="rent" value="0" />
            </td>
            <td>
                <select name="cc">
                    <option value="EUR" selected="selected">EUR</option>
                    <option value="USD">USD</option>
                    <option value="XBT">XBT</option>
                </select>
            </td>
            <td>&nbsp;</td>
            <td>
                <input type="checkbox" name="active" checked="checked" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
<?php } /* admin_manage_payments */ ?>
</table>

<div class="box pad">
<div class="header">
    <h2>Budget Forecast</h2>
</div>
    <table>
        <tr class="colhead">
            <td>&nbsp;</td>
            <td>Monthly</td>
            <td>Quarterly</td>
            <td>Annual</td>
        </tr>
        <tr>
            <td>Budget</td>
            <td><?= sprintf('%0.4f', $totalRent / 12) ?></td>
            <td><?= sprintf('%0.4f', $totalRent /  4) ?></td>
            <td><?= sprintf('%0.4f', $totalRent) ?></td>
        </tr>
        <tr>
            <td>Actual</td>
            <td><?= sprintf('%0.4f', $donorMan->totalMonth( 1)) ?></td>
            <td><?= sprintf('%0.4f', $donorMan->totalMonth( 3)) ?></td>
            <td><?= sprintf('%0.4f', $donorMan->totalMonth(12)) ?></td>
        </tr>
        <tr>
            <td>Target</td>
            <td><?= sprintf('%0.1f%%', $donorMan->totalMonth( 1) / ($totalRent/12) * 100) ?></td>
            <td><?= sprintf('%0.1f%%', $donorMan->totalMonth( 3) / ($totalRent/ 4) * 100) ?></td>
            <td><?= sprintf('%0.1f%%', $donorMan->totalMonth(12) / ($totalRent   ) * 100) ?></td>
        </tr>
    </table>
</div>
<?php

View::show_footer();
