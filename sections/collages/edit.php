<?php

$collageID = (int)$_GET['collageid'];
if ($collageID < 1) {
    error(404);
}
$collage = new Gazelle\Collage($collageID);

if ($collage->categoryId() == 0 && !$collage->isOwner($Viewer->id()) && !check_perms('site_collages_delete')) {
    error(403);
}

View::show_header('Edit collage');

if (!empty($Err)) {
    if (isset($ErrNoEscape)) {
        echo '<div class="save_message error">'.$Err.'</div>';
    } else {
        echo '<div class="save_message error">'.display_str($Err).'</div>';
    }
}
?>
<div class="thin">
    <div class="header">
        <h2>Edit collage <?= $collage->link() ?></h2>
    </div>
    <form class="edit_form" name="collage" action="collages.php" method="post">
        <input type="hidden" name="action" value="edit_handle" />
        <input type="hidden" name="auth" value="<?=$Viewer->auth()?>" />
        <input type="hidden" name="collageid" value="<?=$collageID?>" />
        <table id="edit_collage" class="layout">
<?php
if (check_perms('site_collages_delete') || ($collage->isPersonal() && $collage->isOwner($Viewer->id()) && check_perms('site_collages_renamepersonal'))) { ?>
            <tr>
                <td class="label">Name</td>
                <td><input type="text" name="name" size="60" value="<?=$collage->name()?>" /></td>
            </tr>
<?php
}
if ($collage->categoryId() > 0 || check_perms('site_collages_delete')) { ?>
            <tr>
                <td class="label"><strong>Category</strong></td>
                <td>
                    <select name="category">
<?php
    foreach (COLLAGE as $CatID => $CatName) {
        if (!check_perms('site_collages_delete') && $CatID == 0) {
            // Only mod-type get to make things personal
            continue;
        }
?>
        <option value="<?=$CatID?>"<?=$CatID == $collage->categoryId() ? ' selected="selected"' : ''?>><?=$CatName?></option>
<?php    } ?>
                    </select>
                </td>
            </tr>
<?php
} ?>
            <tr>
                <td class="label">Description</td>
                <td>
                    <textarea name="description" id="description" cols="60" rows="10"><?=$collage->description()?></textarea>
                </td>
            </tr>
            <tr>
                <td class="label">Tags</td>
                <td><input type="text" name="tags" size="60" value="<?=implode(', ', $collage->tags())?>" /></td>
            </tr>
<?php if ($collage->isPersonal()) { ?>
            <tr>
                <td class="label"><span class="tooltip" title="A &quot;featured&quot; personal collage will be listed first on your profile, along with a preview of the included torrents.">Featured</span></td>
                <td><input type="checkbox" name="featured"<?=($collage->isFeatured() ? ' checked="checked"' : '')?> /></td>
            </tr>
<?php
}
if (check_perms('site_collages_delete')) {
?>
            <tr>
                <td class="label">Locked</td>
                <td><input type="checkbox" name="locked" <?=$collage->isLocked() ? 'checked="checked" ' : ''?>/></td>
            </tr>
            <tr>
                <td class="label">Max groups</td>
                <td><input type="text" name="maxgroups" size="5" value="<?=$collage->maxGroups()?>" /></td>
            </tr>
            <tr>
                <td class="label">Max groups per user</td>
                <td><input type="text" name="maxgroupsperuser" size="5" value="<?=$collage->maxGroupsPerUser()?>" /></td>
            </tr>

<?php } ?>
            <tr>
                <td colspan="2" class="center"><input type="submit" value="Edit collage" /></td>
            </tr>
        </table>
    </form>
</div>
<?php
View::show_footer();
