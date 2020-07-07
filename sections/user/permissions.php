<?php
//TODO: Redo HTML
$UserID = (int)$_REQUEST['userid'];
if ($UserID < 1) {
    error(404);
}

// Get the user class of the user being edited to ensure that the logged in user has permission
list($UserClass, $Customs) = $DB->row("
    SELECT p.Level, um.CustomPermissions
    FROM permissions p
    INNER JOIN users_main AS um ON (um.PermissionID = p.ID)
    WHERE um.ID = ?
    ", $UserID
);

if (!check_perms('admin_manage_permissions', $UserClass)) {
    error(403);
}

list($UserID, $Username, $PermissionID) = array_values(Users::user_info($_REQUEST['userid']));

$Defaults = Permissions::get_permissions_for_user($UserID, []);

$Delta = [];
if (!isset($_POST['action'])) {
    $Delta = unserialize($Customs);
} else {
    authorize();

    $PermissionsArray = Permissions::list();
    foreach ($PermissionsArray as $Perm => $Explaination) {
        $Setting = isset($_POST["perm_$Perm"]) ? 1 : 0;
        $Default = isset($Defaults[$Perm]) ? 1 : 0;
        if ($Setting != $Default) {
            $Delta[$Perm] = $Setting;
        }
    }
    if (!is_number($_POST['maxcollages']) && !empty($_POST['maxcollages'])) {
        error("Please enter a valid number of extra personal collages");
    }
    $Delta['MaxCollages'] = $_POST['maxcollages'];

    $Cache->delete_value("user_info_heavy_$UserID");
    $DB->prepared_query("
        UPDATE users_main SET
            CustomPermissions = ?
        WHERE ID = ?
        ", serialize($Delta), $UserID
    );
}

$Permissions = array_merge($Defaults, $Delta);
$MaxCollages = $Customs['MaxCollages'] + $Delta['MaxCollages'];

View::show_header("$Username &rarr; Permissions");
?>
<script type="text/javascript">//<![CDATA[
function reset() {
    for (i = 0; i < $('#permissionsform').raw().elements.length; i++) {
        element = $('#permissionsform').raw().elements[i];
        if (element.id.substr(0, 8) == 'default_') {
            $('#' + element.id.substr(8)).raw().checked = element.checked;
        }
    }
}
//]]>
</script>
<div class="header">
    <h2><?=Users::format_username($UserID, false, false, false)?> &raquo; Permissions</h2>
    <div class="linkbox">
        <a href="#" onclick="reset(); return false;" class="brackets">Defaults</a>
        <a href="tools.php?action=permissions&id=<?= $PermissionID ?>" class="brackets">Primary permissions</a>
    </div>
</div>
<div class="box pad">
    <p>Before using permissions, please understand that it allows you to both add and remove access to specific features. If you think that to add access to a feature, you need to uncheck everything else, <strong>YOU ARE WRONG</strong>. The check boxes on the left, which are grayed out, are the standard permissions granted by their class (and donor/artist status). Any changes you make to the right side will overwrite this. It's not complicated, and if you screw up, click the "Defaults" link at the top. It will reset the user to their respective features granted by class, then you can select or deselect the one or two things you want to change. <strong>DO NOT DESELECT EVERYTHING.</strong> If you need further clarification, ask a developer before using this tool.</p>
</div>
<br />
<form class="manage_form" name="permissions" id="permissionsform" method="post" action="">
    <table class="layout permission_head">
        <tr>
            <td class="label">Extra personal collages</td>
            <td><input type="text" name="maxcollages" size="5" value="<?=($MaxCollages ? $MaxCollages : '0') ?>" /></td>
        </tr>
    </table>
    <input type="hidden" name="action" value="permissions" />
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
    <input type="hidden" name="id" value="<?=$_REQUEST['userid']?>" />
<?= G::$Twig->render('admin/privilege-list.twig', [ 'default' => $Defaults, 'user' => $Permissions ]); ?>
</form>
<?php
View::show_footer();
