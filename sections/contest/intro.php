<?php View::show_header('Orpheus Uploading Contest'); ?>

<div class="linkbox">
    <a href="contest.php?action=leaderboard" class="brackets">Leaderboard</a>
<?php if (check_perms('admin_manage_contest')) { ?>
    <a href="contest.php?action=admin" class="brackets">Admin</a>
<?php } ?>
</div>

<?php
echo $Twig->render('contest/intro.twig', [
    'contest' => $contestMan->currentContest(),
]);

View::show_footer();
