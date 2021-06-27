<?php

$DB->prepared_query("
    SELECT ID, Name
    FROM staff_groups
    ORDER BY Sort");
$groups = $DB->to_array(false, MYSQLI_ASSOC);

View::show_header('Manage Permissions', 'validate');

$Val = new Gazelle\Util\Validator;
echo $Val->generateJS('permissionsform');
?>
<form class="manage_form" name="permissions" id="permissionsform" method="post" action="" onsubmit="return formVal();">
    <input type="hidden" name="action" value="permissions" />
    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
    <input type="hidden" name="id" value="<?=display_str($_REQUEST['id']); ?>" />
    <div class="linkbox">
        <a href="tools.php?action=permissions" class="brackets">Back to permission list</a>
        <a href="tools.php?action=privilege_matrix" class="brackets">Privilege Matrix</a>
        <a href="tools.php" class="brackets">Back to tools</a>
    </div>
    <table class="permission_head layout">
        <tr>
            <td class="label">Permission name</td>
            <td><input type="text" name="name" id="name" value="<?=!empty($name) ? display_str($name) : ''?>" /></td>
        </tr>
        <tr>
            <td class="label">Class level</td>
            <td><input type="text" name="level" id="level" value="<?=!empty($level) ? display_str($level) : ''?>" /></td>
        </tr>
        <tr>
            <td class="label">Secondary class</td>
            <td><input type="checkbox" name="secondary" value="1"<?=!empty($secondary) ? ' checked="checked"' : ''?> /></td>
        </tr>
        <tr>
            <td class="label">Show on staff page</td>
            <td><input type="checkbox" name="displaystaff" value="1"<?=!empty($displayStaff) ? ' checked="checked"' : ''?> /></td>
        </tr>
        <tr>
            <td class="label">Staff page group</td>
            <td>
                <select name="staffgroup" id="staffgroup">
<?php foreach ($groups as $group) { ?>
                    <option value="<?=$group['ID']?>"<?=$group['ID'] == $staffGroup ? ' selected="selected"' : ''?>><?=$group['Name']?></option>
<?php } ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="label">Additional forums</td>
            <td><input type="text" size="30" name="forums" value="<?=display_str($forums)?>" /></td>
        </tr>
<?php if ($secondary) { ?>
        <tr>
            <td class="label">Badge</td>
            <td><input type="text" size="30" name="badge" value="<?=$badge?>" /></td>
        </tr>
<?php
    }
    if (is_numeric($id)) {
?>
        <tr>
            <td class="label">Current users in this class</td>
            <td><?= number_format($userCount) ?>&nbsp;<a href="/user.php?action=search&class[]=<?= $id ?>" class="brackets">View</a></td>
        </tr>
<?php } ?>
    </table>
<?= $Twig->render('admin/privilege-list.twig', [ 'default' => null, 'user' => $values ]); ?>
</form>
<?php
View::show_footer();
