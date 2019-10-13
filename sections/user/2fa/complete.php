<?php
View::show_header('Two-factor Authentication');

$UserID = $_REQUEST['userid'];
if (!is_number($UserID)) {
    error(404);
}

$DB->query("SELECT Recovery FROM users_main WHERE ID = '" . db_string($UserID) . "'");

list($Recovery) = $DB->next_record(MYSQLI_NUM, false);

// don't worry about the permission check, we did that in the controller :)
?>

<div class="box pad">
    <p>Please note that if you lose your 2FA key and all of your backup keys, the <?= SITE_NAME ?> staff cannot help you
        retrieve your account. Ensure you keep your backup keys in a safe place.</p>
</div>

<div class="box pad">
    <p>Two-factor authentication has now been enabled on your account. Please note down the following recovery keys, they are the only way you will be able to recover your account if you lose your hardware device.</p>

    <ul class="pad">
        <?php foreach(unserialize($Recovery) as $r): ?>
            <li><?= $r ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<?php View::show_footer(); ?>
