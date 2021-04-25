<?php

if (!isset($_GET['userid'])) {
    $userId = $LoggedUser['ID'];
} else {
    if (!check_perms('users_view_invites')) {
        error(403);
    }
    $userId = (int)$_GET['userid'];
}

// Cannot use Users::user_info() because an inexistent ID will return 'Unknown'
[$userId, $Username] = $DB->row("
    SELECT ID, Username
    FROM users_main
    WHERE ID = ?
    ", $userId
);
if (!$userId) {
    error(404);
}

$tree = new Gazelle\InviteTree($userId);
View::show_header($Username.' &rsaquo; Invites &rsaquo; Tree');
?>
<div class="thin">
    <div class="header">
        <h2><?=Users::format_username($userId, false, false, false)?> &rsaquo; <a href="user.php?action=invite&amp;userid=<?=$userId?>">Invites</a> &rsaquo; Tree</h2>
    </div>
    <div class="box pad">
        <?= $tree->render($Twig) ?>
    </div>
</div>
<?php
View::show_footer();
