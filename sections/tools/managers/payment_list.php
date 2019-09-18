<?php
if (!check_perms('admin_manage_payments')) {
    error(403);
}

$DB->prepared_query("
        SELECT ID, Text, Expiry, Active
        FROM payment_reminders");

$Reminders = $DB->has_results() ? $DB->to_array('ID', MYSQLI_ASSOC) : [];

View::show_header('Payment Dates');
?>
<div class="header">
    <h2>Payment Dates</h2>
</div>
<table>
    <tr class="colhead">
        <td>Payment</td>
        <td>Expiry</td>
        <td>Active</td>
        <td>Submit</td>
    </tr>
<?php
$Row = 'b';
foreach ($Reminders as $r) {
    list($ID, $Text, $Expiry, $Active) = array_values($r);
    $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$Row?>">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="payment_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" name="text" value="<?=$Text?>" />
            </td>
            <td>
                <input type="text" name="expiry" value="<?=date('Y-m-d', strtotime($Expiry))?>" placeholder="YYYY-MM-DD" />
            </td>
            <td>
                <input type="checkbox" name="active"<?=($Active == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this payment? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php } ?>
    <tr class="colhead">
        <td colspan="4">Create Payment</td>
    </tr>
    <tr class="rowa">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="action" value="payment_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" size="15" name="text" value="" />
            </td>
            <td>
                <input type="text" size="10" name="expiry" value="" placeholder="YYYY-MM-DD" />
            </td>
            <td>
                <input type="checkbox" name="active" checked="checked" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
</table>
<?php
    View::show_footer();
?>
