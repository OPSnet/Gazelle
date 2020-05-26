<?php
if (!check_perms('users_mod')) {
    error(403);
}
$Message = "";
if (isset($_REQUEST['add_points'])) {
    authorize();
    $Points = floatval($_REQUEST['num_points']);

    if ($Points <= 0) {
        error('Please enter a positive number of points.');
    }

    $Bonus = new \Gazelle\Bonus;
    $enabledCount = $Bonus->addGlobalPoints($Points);
    $Message = '<strong>' . number_format($Points) . ' bonus points added to ' . number_format($enabledCount) . ' enabled users.</strong><br /><br />';
}

View::show_header('Add tokens sitewide');

?>
<div class="header">
    <h2>Add bonus points to all enabled users</h2>
</div>
<div class="box pad" style="margin-left: auto; margin-right: auto; text-align: center; max-width: 40%;">
    <?=$Message?>
    <form class="add_form" name="fltokens" action="" method="post">
        <input type="hidden" name="action" value="bonus_points" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        Points to add: <input type="text" name="num_points" size="10" style="text-align: right;" /><br /><br />
        <input type="submit" name="add_points" value="Add points" />
    </form>
</div>
<br />
<?php
View::show_footer();
