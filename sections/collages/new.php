<?php
View::show_header('Create a collage');

$ChangeJS = '';
if (!check_perms('site_collages_renamepersonal')) {
    $ChangeJS = " onchange=\"if ( this.options[this.selectedIndex].value == '0') { $('#namebox').ghide(); $('#personal').gshow(); } else { $('#namebox').gshow(); $('#personal').ghide(); }\"";
}

$NoName = !check_perms('site_collages_renamepersonal') && $Category === '0';

$user = new \Gazelle\User($LoggedUser['ID']);
$personalAllowed = $user->canCreatePersonalCollage();
?>
<div class="thin">
<?php if (isset($Err)) { ?>
    <div class="save_message error"><?=$Err?></div>
    <br />
<?php } ?>
    <form class="create_form" name="collage" action="collages.php" method="post">
        <input type="hidden" name="action" value="new_handle" />
        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
        <table class="layout">
            <tr id="collagename">
                <td class="label"><strong>Name</strong></td>
                <td>
                    <input type="text"<?= $NoName ? ' class="hidden"' : ''; ?> name="name" size="60" id="namebox" value="<?=display_str($Name ?? '')?>" />
                    <span id="personal"<?= $NoName ? '' : ' class="hidden"'; ?> style="font-style: oblique;"><strong><?=$LoggedUser['Username']?>'s personal collage</strong></span>
                </td>
            </tr>
            <tr>
                <td class="label" style="vertical-align: top;"><strong>Category</strong></td>
                <td>
                    <select name="category"<?=$ChangeJS?>>
<?php foreach ($CollageCats as $CatID => $CatName) {
    if ($CatID == 0 && !$personalAllowed) {
        continue;
    }
?>
                        <option value="<?= $CatID ?>"<?= $CatID == ($Category ?? '') ? ' selected="selected"' : '' ?>><?= $CatName ?></option>
<?php } ?>
                    </select>
                    <br />
<?= $Twig->render('collage/description.twig', [ 'personal_allowed' => $personalAllowed, ]) ?>
                </td>
            </tr>
            <tr>
                <td class="label">Description</td>
                <td>
                    <?= (new Gazelle\Util\Textarea('description', display_str($Description ?? ''), 60, 10))->emit() ?>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Tags (comma-separated)</strong></td>
                <td>
                    <input type="text" id="tags" name="tags" size="60" value="<?=display_str($Tags ?? '')?>" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <strong>Please ensure your collage will be allowed under the <a href="rules.php?p=collages">Collage Rules</a>.</strong>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center"><input type="submit" value="Create collage" /></td>
            </tr>
        </table>
    </form>
</div>
<?php
View::show_footer();
