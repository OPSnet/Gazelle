<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$DB->prepared_query("
    SELECT ID, Sort, Name
    FROM staff_groups
    ORDER BY Sort");
$Groups = $DB->to_array(false, MYSQLI_ASSOC);

View::show_header('Staff Group Management');
?>
<div class="header">
    <h2>Staff Group Management</h2>
</div>
<table>
    <tr class="colhead">
        <td>Sort</td>
        <td>Name</td>
        <td>Submit</td>
    </tr>
<?php
    $Row = 'b';
    foreach ($Groups as $g) {
        list($ID, $Sort, $Name) = array_values($g);
        $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?php $Row?>">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="staff_groups_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="10" name="sort" value="<?=$Sort?>" />
            </td>
            <td>
                <input type="text" size="30" name="name" value="<?=$Name?>" />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this group? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php
}
?>
    <tr class="colhead">
        <td colspan="3">Create Group</td>
    </tr>
    <tr class="rowa">
        <form class="create_form" name="groups" action="" method="post">
            <input type="hidden" name="action" value="staff_groups_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="10" name="sort" />
            </td>
            <td>
                <input type="text" size="30" name="name" />
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
