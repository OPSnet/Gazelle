<?php
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

function type_list(array $Types, int $Selected = 0): string {
    $Ret = '';
    foreach ($Types as $id => $name) {
        $Ret .= "<option value=\"$id\"";
        if ($Selected == $id) {
            $Ret .= ' selected="selected"';
        }
        $Ret .= ">$name</option>\n";
    }
    return $Ret;
}

/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('admin_manage_referrals')) {
    error(403);
}

$ReferralManager = new Gazelle\Manager\Referral();
$ReferralAccounts = $ReferralManager->getFullAccounts();

View::show_header('Referral Accounts');
?>
<div class="header">
    <h2>Referral account manager</h2>
<?php if ($ReferralManager->readOnly) { ?>
    <p>
        <strong class="important_text">DB key not loaded or incorrect - editing suspended</strong>
    </p>
<?php } ?>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Site</td>
        <td>URL</td>
        <td>User</td>
        <td>Password</td>
        <td>Type</td>
        <td>Active</td>
        <td>Cookie</td>
        <td>Submit</td>
    </tr>
<?php
$Row = 'b';
foreach ($ReferralAccounts as $a) {
    [$ID, $Site, $URL, $User, $Password, $Active, $Type, $Cookie] = array_values($a);
    $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$Row?>">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="referral_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="10" name="site" value="<?=$Site?>" />
            </td>
            <td>
                <input type="text" size="15" name="url" value="<?=$URL?>" />
            </td>
            <td>
                <input type="text" size="10" name="user" value="<?=$User?>" />
            </td>
            <td>
                <input type="password" size="10" name="password" />
            </td>
            <td>
                <select name="type">
                    <?=type_list($ReferralManager->getTypes(), $Type)?>
                </select>
            </td>
            <td>
                <input type="checkbox" name="active"<?=($Active == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="text" size="10" name="cookie" />
            </td>
            <td>
                <?php if (!$ReferralManager->readOnly) { ?>
                <input type="submit" name="submit" value="Edit" />
                <?php } ?>
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this account? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php
}
if (!$ReferralManager->readOnly) {
?>
    <tr class="colhead">
        <td colspan="8">Create Account</td>
    </tr>
    <tr class="rowa">
        <form class="create_form" name="accounts" action="" method="post">
            <input type="hidden" name="action" value="referral_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="10" name="site" />
            </td>
            <td>
                <input type="text" size="15" name="url" />
            </td>
            <td>
                <input type="text" size="10" name="user" />
            </td>
            <td>
                <input type="password" size="10" name="password" />
            </td>
            <td>
                <select name="type">
                    <?=type_list($ReferralManager->getTypes())?>
                </select>
            </td>
            <td>
                <input type="checkbox" name="active" checked="checked" />
            </td>
            <td>
                <input type="text" size="10" name="cookie" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
<?php
} ?>
</table>
<?php
    View::show_footer();
?>
