<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

View::show_header('Manage Permissions');
?>
<script type="text/javascript">//<![CDATA[
function confirmDelete(id) {
    if (confirm("Are you sure you want to remove this permission class?")) {
        location.href = "tools.php?action=permissions&removeid=" + id;
    }
    return false;
}
//]]>
</script>
<div class="thin">
    <div class="header">
        <div class="linkbox">
            <a href="tools.php?action=permissions&amp;id=new" class="brackets">Create a new permission set</a>
            <a href="tools.php?action=privilege_matrix" class="brackets">Privilege Matrix</a>
            <a href="tools.php" class="brackets">Back to tools</a>
        </div>
    </div>
<?php
$DB->prepared_query("
    SELECT
        p.ID,
        p.Name,
        p.Level,
        p.Secondary,
        count(u.ID) + count(DISTINCT l.UserID)
    FROM permissions AS p
    LEFT JOIN users_main AS u ON (u.PermissionID = p.ID)
    LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID)
    GROUP BY p.ID
    ORDER BY p.Secondary ASC, p.Level ASC
");
if (!$DB->has_results()) { ?>
    <h2 align="center">There are no permission classes.</h2>
<?php } else { ?>
    <table width="100%">
        <tr class="colhead">
            <td>Name</td>
            <td>Level</td>
            <td>User count</td>
            <td class="center">Actions</td>
        </tr>
<?php
    while (list($id, $name, $level, $secondary, $userCount) = $DB->next_record()) {
        $part = $secondary ? 'secclass' : 'class[]';
        $link = "user.php?action=search&{$part}={$id}";
?>
        <tr>
            <td><a href="<?=$link?>"><?=display_str($name)?></a></td>
            <td><?=($secondary ? 'Secondary' : $level)?></td>
            <td><a href="<?=$link?>"><?=number_format($userCount)?></a></td>
            <td class="center">
                <a href="tools.php?action=permissions&amp;id=<?=$id?>" class="brackets">Edit</a>
<?php
        if (!$userCount) {
?>
                <a href="#" onclick="return confirmDelete(<?=$id?>);" class="brackets">Remove</a>
<?php
        }
?>
            </td>
        </tr>
<?php } ?>
    </table>
<?php } ?>
</div>
<?php
View::show_footer();
