<?
if (!check_perms('admin_manage_applicants')) {
    error(403);
}
View::show_header('Applicant administration');
$EDIT_ID = 0;
$Saved   = '';
if (isset($_POST['auth'])) {
    authorize();
    $edit = array_filter($_POST, function ($x) { return preg_match('/^edit-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
    if (is_array($edit) && count($edit) == 1) {
        $EDIT_ID = trim(array_keys($edit)[0], 'edit-');
        $AppRole = ApplicantRole::factory($EDIT_ID);
    }
    elseif (isset($_POST['edit']) && is_numeric($_POST['edit'])) {
        $EDIT_ID = intval($_POST['edit']);
        $AppRole = ApplicantRole::factory($EDIT_ID);
        if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
            if ($user_id == $LoggedUser['ID']) {
                $AppRole->update(
                    $_POST['title'],
                    $_POST['description'],
                    (isset($_POST['status']) && is_numeric($_POST['status']) && $_POST['status'] == 1)
                );
            }
            $EDIT_ID = 0; /* return to list */
            $Saved = 'updated';
        }
    }
    else {
        $AppRole = new ApplicantRole(
            $_POST['title'],
            $_POST['description'],
            (isset($_POST['status']) && is_numeric($_POST['status']) && $_POST['status'] == 1),
            $LoggedUser['ID']
        );
        $Saved = 'saved';
    }
}
?>

<div class="thin">

<div class="linkbox">
    <a href="/apply.php" class="brackets">Apply</a>
    <a href="/apply.php?action=view" class="brackets">Current applications</a>
    <a href="/apply.php?action=view&status=resolved" class="brackets">Resolved applications</a>
</div>

<h3>Manage roles at <?=SITE_NAME?></h3>

<form method="post" action="/apply.php?action=admin">

<div class="box">
    <div class="head">Current Roles</div>
    <div class="pad">
<?  if ($Saved) { ?>
        <p>The role <?= $AppRole->title() ?> was <?= $Saved ?>.</p>
<? } ?>

<? if (!$EDIT_ID) {
    $Roles = ApplicantRole::get_list(true);
    if (count($Roles)) {
?>
        <table>
<?        foreach ($Roles as $title => $info) { ?>
            <tr>
                <td>
                    <div class="head">
                        <div style="float: right;">
                            <input style="margin-bottom: 10px;" type="submit" name="edit-<?= $info['id'] ?>" value="Edit" />
                        </div>
                        <?= $title ?> (<?=  $info['published'] ? 'published' : 'archived' ?>)
                        <br />Role created <?= time_diff($info['created'], 2) ?> by
                        <?=
                            Users::format_username($info['user_id'])
                            . ($info['modified'] == $info['created'] ? '' : ', last modified ' . time_diff($info['modified'], 2))
                        ?>.
                    </div>
                </td>
            </tr>
            <tr>
                <td><div class="pad"><?= Text::full_format($info['description']) ?></div></td>
            </tr>
<?        } /* foreach */ ?>
        </table>
<?    } else { ?>
        <p>There are no current roles. Create one using the form below.</p>
<?    }
} /* !$EDIT_ID */ ?>
    </div>
</div>

<div class="box">
    <div class="head"><?= $EDIT_ID ? 'Edit' : 'New' ?> Role</div>
    <div class="pad">

<?
if (isset($App)) {
    $checked_published = $AppRole->is_published() ? ' checked' : '';
    $checked_archived  = $AppRole->is_published() ? '' : ' checked';
}
else {
    $checked_published = '';
    $checked_archived  = ' checked';
}
?>
        <table>
            <tr>
                <td class="label">Title</td>
                <td><input type="text" width="100" name="title" value="<?= $EDIT_ID ? $AppRole->title() : '' ?>" /></td>
            </tr>
            <tr>
                <td class="label">Visibility</td>
                <td>
                    <input type="radio" name="status" value="1" id="status-pub"<?= $checked_published ?> /><label for="status-pub">published</label><br />
                    <input type="radio" name="status" value="0" id="status-arch"<?= $checked_archived ?> /><label for="status-arch">archived</label>
                </td>
            </tr>
            <tr>
                <td class="label">Description</td>
                <td>
<?
                    $text = new TEXTAREA_PREVIEW('description', 'description', $EDIT_ID ? $AppRole->description() : '', 60, 8, true, false);
                    $id = $text->getID();
                    echo $text->preview();
?>
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>"/>
<?  if ($EDIT_ID) { ?>
                    <input type="hidden" name="edit" value="<?= $EDIT_ID ?>"/>
<?  } ?>
                    <input type="hidden" name="user_id" value="<?= $LoggedUser['ID'] ?>"/>
                    <input type="button" value="Preview" class="hidden button_preview_<?= $text->getId() ?>" />
                    <input type="submit" id="submit" value="Save Role"/>
                </td>
            </tr>
        </table>
    </div>
</div>

</form>

</div>
<?
View::show_footer();
