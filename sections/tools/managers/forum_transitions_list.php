<?php
function forumList($forums, $selected = 0) {
    $return = '';
    $cat = '';
    foreach ($forums as $id => $forum) {
        if ($cat !== $forum['Category']) {
            if ($cat !== '') {
                $return .= '</optgroup>';
            }
            $cat = $forum['Category'];
            $return .= sprintf('<optgroup label="%s">', $cat);
        }

        $return .= sprintf('<option value="%s"%s>%s</option>', $id, $id == $selected ? ' selected="selected"' : '', $forum['Name']);
    }
    if ($cat !== '') {
        $return .= '</optgroup>';
    }

    return $return;
}

if (!check_perms('admin_manage_forums')) {
    error(403);
}

$DB->prepared_query('
    SELECT f.ID, f.Name, fc.Name AS Category
    FROM forums f
    INNER JOIN forums_categories fc ON f.CategoryID = fc.ID
    ORDER BY fc.Sort, f.Sort');
$forums = $DB->to_array('ID', MYSQLI_ASSOC);
$Debug->log_var($forums);

$items = Forums::get_transitions();

View::show_header('Forum Transitions');
?>
<div class="header">
    <h2>Forum transition manager</h2>
    <h4>AKA lazy button creator</h4>
</div>
<table>
    <tr class="colhead">
        <td>Source</td>
        <td>Destination</td>
        <td>Label</td>
        <td>Permissions</td>
        <td>Submit</td>
    </tr>
<?php
$row = 'b';
foreach ($items as $i) {
    list($id, $source, $destination, $label, $permissions) = array_values($i);
    $row = $row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$row?>">
        <form class="manage_form" name="navitems" action="" method="post">
            <input type="hidden" name="id" value="<?=$id?>" />
            <input type="hidden" name="action" value="forum_transitions_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <select name="source">
                    <?=forumList($forums, $source)?>
                </select>
            </td>
            <td>
                <select name="destination">
                    <?=forumList($forums, $destination)?>
                </select>
            </td>
            <td>
                <input type="text" name="label" value="<?=$label?>" />
            </td>
            <td>
                <input type="text" name="permissions" value="<?=$permissions?>" />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this transition? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php } ?>
    <tr>
        <td colspan="5">Create Transition</td>
    </tr>
    <tr class="rowa">
        <form class="manage_form" name="navitems" action="" method="post">
            <input type="hidden" name="action" value="forum_transitions_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <select name="source">
                    <?=forumList($forums)?>
                </select>
            </td>
            <td>
                <select name="destination">
                    <?=forumList($forums)?>
                </select>
            </td>
            <td>
                <input type="text" name="label" />
            </td>
            <td>
                <input type="text" name="permissions" />
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
