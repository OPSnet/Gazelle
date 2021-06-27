<?php
if (!check_perms('admin_periodic_task_manage')) {
    error(403);
}

$scheduler = new \Gazelle\Schedule\Scheduler;
$tasks = $scheduler->getTasks();
$canEdit = true;

View::show_header('Periodic Task Manager');
?>
<div class="header">
    <h2>Periodic Task Manager</h2>
</div>
<?php
include(SERVER_ROOT.'/sections/tools/development/periodic_links.php');

if (isset($err)) { ?>
<strong class="important_text"><?=$err?></strong>
<?php } ?>
<table width="100%" id="tasks">
    <tr class="colhead">
        <td>Name</td>
        <td>Class Name</td>
        <td>Description</td>
        <td>Interval</td>
        <td>Enabled</td>
        <td>Sane</td>
        <td>Debug</td>
        <td></td>
    </tr>
<?php
$row = 'b';
foreach ($tasks as $task) {
    list($id, $name, $classname, $description, $period, $isEnabled, $isSane, $isDebug) = array_values($task);
    $row = $row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$row?>">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="id" value="<?=$id?>" />
            <input type="hidden" name="action" value="periodic" />
            <input type="hidden" name="mode" value="alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="15" name="name" value="<?=$name?>" />
            </td>
            <td>
                <input type="text" size="15" name="classname" value="<?=$classname?>" />
            </td>
            <td>
                <input type="text" size="40" name="description" value="<?=$description?>" />
            </td>
            <td>
            <input type="text" size="7" name="interval" value="<?=$period?>" />
            </td>
            <td>
                <input type="checkbox" name="enabled"<?=($isEnabled == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="checkbox" name="sane"<?=($isSane == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="checkbox" name="debug"<?=($isDebug == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this task? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php
}
?>
    <tr class="colhead">
        <td colspan="8">Create Task</td>
    </tr>
    <tr class="rowa">
        <form class="create_form" name="accounts" action="" method="post">
            <input type="hidden" name="action" value="periodic" />
            <input type="hidden" name="mode" value="alter" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <td>
                <input type="text" size="10" name="name" />
            </td>
            <td>
                <input type="text" size="15" name="classname" />
            </td>
            <td>
                <input type="text" size="10" name="description" />
            </td>
            <td>
            <input type="text" size="10" name="interval" />
            </td>
            <td>
                <input type="checkbox" name="enabled" checked="checked" />
            </td>
            <td>
                <input type="checkbox" name="sane" checked="checked" />
            </td>
            <td>
                <input type="checkbox" name="debug" />
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
