<?php
function forumList(array $list, $selected = 0) {
    $return = '';
    $cat = '';
    foreach ($list as $forum) {
        if ($cat !== $forum->categoryName()) {
            if ($cat !== '') {
                $return .= '</optgroup>';
            }
            $cat = $forum->categoryName();;
            $return .= sprintf('<optgroup label="%s">', $cat);
        }

        $return .= sprintf('<option value="%s"%s>%s</option>', $forum->id(), $forum->id() == $selected ? ' selected="selected"' : '', $forum->name());
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

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

$user = null;
if (isset($_REQUEST['userid'])) {
    $user = (new Gazelle\Manager\User)->find($_REQUEST['userid']);
} else {
    $user = $Viewer;
}
if (is_null($user)) {
    error(404);
}
$userId = $user->id();

$forumMan = new Gazelle\Manager\Forum;
$forumList = array_map(function ($f) {return new Gazelle\Forum($f);}, $forumMan->forumList());
$items = $forumMan->forumTransitionList($user);

View::show_header('Forum Transitions');
?>
<div class="header">
    <h2>Forum transition manager</h2>
</div>
<div class="linkbox">
    <a class="brackets" href="tools.php?action=categories">Forum Categories</a>
    <a class="brackets" href="tools.php?action=forum">Forum Control Panel</a>
</div>
<div class="thin box">
    <h4>Preview transitions</h4>
    <form class="manage_form" name="preview" action="" method="get">
        <input type="hidden" name="action" value="forum_transitions" />
        <table class="layout">
            <tr>
                <td class="label"><label for="userid">User ID (or @username)</label></td>
                <td><input type="text" name="userid" value="<?= $userId ?>" /> <?= $user->username() ?></td>
                <td><input type="submit" name="submit" value="Preview" class="submit" /></td>
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
    [$id, $source, $destination, $label, $secondaryClasses, $userClass, $permissions, $userIds] = array_values($i);
    $row = $row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$row?>">
        <form class="manage_form" name="navitems" action="" method="post">
            <input type="hidden" name="id" value="<?=$id?>" />
            <input type="hidden" name="action" value="forum_transitions_alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <select name="source">
                    <?=forumList($forumList, $source)?>
                </select>
            </td>
            <td>
                <select name="destination">
                    <?=forumList($forumList, $destination)?>
                </select>
            </td>
            <td>
                <input type="text" name="label" value="<?=$label?>" />
            </td>
            <td>
                <input type="text" name="secondary_classes" value="<?= implode(',', array_keys($secondaryClasses)) ?>" />
            </td>
            <td>
                <select name="permission_class">
                    <?=classList($userClass)?>
                </select>
            </td>
            <td>
                <input type="text" name="permissions" value="<?= implode(',', array_keys($permissions)) ?>" />
            </td>
            <td>
                <input type="text" name="user_ids" value="<?= implode(',', array_keys($userIds)) ?>" />
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
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <select name="source">
                    <?=forumList($forumList)?>
                </select>
            </td>
            <td>
                <select name="destination">
                    <?=forumList($forumList)?>
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
