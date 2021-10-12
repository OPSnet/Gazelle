<?php

if (!$Viewer->permitted('admin_manage_navigation')) {
    error(403);
}

$Items = Users::get_nav_items();

View::show_header('Navigation Links');
?>
<div class="header">
    <h2>Navigation link manager</h2>
</div>
<table>
    <tr class="colhead">
        <td>Tag</td>
        <td>Title</td>
        <td>Target</td>
        <td>Tests</td>
        <td>Test User</td>
        <td>Mandatory</td>
        <td>Default</td>
        <td>Submit</td>
    </tr>
<?php
$Row = 'b';
foreach ($Items as $i) {
    list($ID, $Tag, $Title, $Target, $Tests, $TestUser, $Mandatory, $Initial) = array_values($i);
    $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$Row?>">
        <form class="manage_form" name="navitems" action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="navigation_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" name="tag" value="<?=$Tag?>" />
            </td>
            <td>
                <input type="text" name="title" value="<?=$Title?>" />
            </td>
            <td>
                <input type="text" name="target" value="<?=$Target?>" />
            </td>
            <td>
                <input type="text" name="tests" value="<?=$Tests?>" />
            </td>
            <td>
                <input type="checkbox" name="testuser"<?=($TestUser == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="checkbox" name="mandatory"<?=($Mandatory == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="checkbox" name="default"<?=($Initial == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this link? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php
} ?>
    <tr>
        <td colspan="5">Create Link</td>
    </tr>
    <tr class="rowa">
        <form class="manage_form" name="navitems" action="" method="post">
            <input type="hidden" name="action" value="navigation_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" name="tag" />
            </td>
            <td>
                <input type="text" name="title" />
            </td>
            <td>
                <input type="text" name="target" />
            </td>
            <td>
                <input type="text" name="tests" />
            </td>
            <td>
                <input type="checkbox" name="testuser" />
            </td>
            <td>
                <input type="checkbox" name="mandatory" />
            </td>
            <td>
                <input type="checkbox" name="default" />
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
