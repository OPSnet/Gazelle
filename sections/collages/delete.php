<?php

$CollageID = (int)$_GET['collageid'];
if ($CollageID < 1) {
    error(404);
}
$Collage = new Gazelle\Collage($_GET['collageid']);

if (!check_perms('site_collages_delete') && !$Collage->isOwner($LoggedUser['ID']) && !$Collage->isDeleted()) {
    error(403);
}

View::show_header('Delete collage');
?>
<div class="thin center">
    <div class="box" style="width: 600px; margin: 0px auto;">
        <div class="head colhead">
            Delete collage
        </div>
        <div class="pad">
            <form class="delete_form" name="collage" action="collages.php" method="post">
                <input type="hidden" name="action" value="take_delete" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="collageid" value="<?=$CollageID?>" />
<?php if ($CategoryID == 0) { ?>
                <div class="alertbar" style="margin-bottom: 1em;">
                    <strong>Warning: This is a personal collage. If you delete this collage, it <em>cannot</em> be recovered!</strong>
                </div>
<?php } ?>
                <div class="field_div">
                    <strong>Reason: </strong>
                    <input type="text" name="reason" size="40" />
                </div>
                <div class="submit_div">
                    <input value="Delete" type="submit" />
                </div>
            </form>
        </div>
    </div>
</div>
<?php
View::show_footer();
