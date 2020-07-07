<?php

if (!isset($_GET['userid'])) {
    $UserCount = Users::get_enabled_users_count();
    $UserID = $LoggedUser['ID'];
    $Sneaky = false;
} else {
    if (!check_perms('users_view_invites')) {
        error(403);
    }    
    $UserID = (int)$_GET['userid'];
    if ($UserID < 1) {
        error(404);
    }
    $Sneaky = true;
}

list($UserID, $Username) = array_values(Users::user_info($UserID));

$Tree = new INVITE_TREE($UserID);

View::show_header($Username.' &rsaquo; Invites &rsaquo; Tree');
?>
<div class="thin">
    <div class="header">
        <h2><?=Users::format_username($UserID, false, false, false)?> &rsaquo; <a href="user.php?action=invite&amp;userid=<?=$UserID?>">Invites</a> &rsaquo; Tree</h2>
    </div>
    <div class="box pad">
<?php $Tree->make_tree(); ?>
    </div>
</div>
<?php
View::show_footer();
