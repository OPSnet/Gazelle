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

function classList($Selected = 0) {
    $Return = '';
    $Classes = (new Gazelle\Manager\User)->classList();
    foreach ($Classes as $ID => $Class) {
        if ($Class['Secondary']) {
            continue;
        }

        $Name = $Class['Name'];
        $Level = $Class['Level'];
        $Return .= "<option value=\"$Level\"";
        if ($Selected == $Level) {
            $Return .= ' selected="selected"';
        }
        $Return .= '>'.shortenString($Name, 20, true)."</option>\n";
    }
    return $Return;
}

if (!check_perms('admin_manage_forums')) {
    error(403);
}

if (isset($_GET['userid'])) {
    $user = $_GET['userid'];
} else {
    $user = $LoggedUser['ID'];
}

$DB->prepared_query('
    SELECT f.ID, f.Name, fc.Name AS Category
    FROM forums f
    INNER JOIN forums_categories fc ON f.CategoryID = fc.ID
    ORDER BY fc.Sort, f.Sort');
$forums = $DB->to_array('ID', MYSQLI_ASSOC);

$items = Forums::get_transitions($user);

View::show_header('Forum Transitions');
?>
<div class="header">
    <h2>Forum transition manager</h2>
</div>
<div class="thin box">
    <h4>Preview transitions</h4>
    <form class="manage_form" name="preview" action="" method="get">
        <input type="hidden" name="action" value="forum_transitions" />
        <table class="layout">
            <tr>
                <td class="label"><label for="userid">User ID</label></td>
                <td><input type="text" name="userid" value="<?=$user?>" /></td>
                <td><input type="submit" name="submit" value="Submit" class="submit" /></td>
            <tr>
        </table>
    </form>
</div>
<h4 class="center">All permission columns are ORed to check for access, any value with a minus (-) in front will invalidate the transition for the user regardless of other permissions</h4>
<table>
    <tr class="colhead">
        <td>Source</td>
        <td>Destination</td>
        <td>Label</td>
        <td>Secondary Classes</td>
        <td>User Class</td>
        <td>Permissions</td>
        <td>User IDs</td>
        <td>Submit</td>
    </tr>
<?php
$row = 'b';
foreach ($items as $i) {
    list($id, $source, $destination, $label, $secondaryClasses, $userClass, $permissions, $userIds) = array_values($i);
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
                <input type="text" name="secondary_classes" value="<?=$secondaryClasses?>" />
            </td>
            <td>
                <select name="permission_class">
                    <?=classList($userClass)?>
                </select>
            </td>
            <td>
                <input type="text" name="permissions" value="<?=$permissions?>" />
            </td>
            <td>
                <input type="text" name="user_ids" value="<?=$userIds?>" />
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
                <input type="text" name="secondary_classes" />
            </td>
            <td>
                <select name="permission_class">
                    <?=classList()?>
                </select>
            </td>
            <td>
                <input type="text" name="permissions" />
            </td>
            <td>
                <input type="text" name="user_ids" />
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
