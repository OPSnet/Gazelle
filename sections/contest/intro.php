<?php

View::show_header('Orpheus Uploading Contest');
$Contest = $ContestMgr->get_current_contest();

if ($Contest !== false and strlen($Contest['Banner'])) {
?>
<div class="pad">
    <img border="0" src="<?=$Contest['Banner'] ?>" alt="<?=$Contest['Name'] ?>" width="640" height="125" style="display: block; margin-left: auto; margin-right: auto;"/>
</div>
<?php
}
?>
<div class="linkbox">
    <a href="contest.php?action=leaderboard" class="brackets">Leaderboard</a>
    <?=(check_perms('admin_manage_contest')) ? '<a href="contest.php?action=admin" class="brackets">Admin</a>' : ''?>
</div>

<?php
if ($Contest === false) {
?>
<div class="thin">
    <div class="box pad" style="padding: 10px 10px 10px 20px;">
        <p>There is no contest at the moment.</p>
    </div>
    </div>
<?php
} else {
?>
<div class="box pad">
    <?= Text::full_format($Contest['WikiText']) ?>
</div>
<?php
}
View::show_footer();
