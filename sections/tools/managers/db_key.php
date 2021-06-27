<?php

if (!check_perms('admin_site_debug')) {
    error(403);
}

if (isset($_POST['dbkey'])) {
    authorize();
    apcu_store('DB_KEY', hash('sha512', $_POST['dbkey']));
}
$fingerprint = (apcu_exists('DB_KEY') && apcu_fetch('DB_KEY'))
    ? '0x' . substr(apcu_fetch('DB_KEY'), 0, 4)
    : false;

View::show_header('Database Encryption Key');
?>

<div class="header">
    <h2>Database Encryption Key</h2>
</div>
<div class="thin box pad">
    <h3>DB key <?= $fingerprint ? " $fingerprint loaded" : "not loaded" ?></h3>
    <form class="create_form" name="db_key" method="post" action="">
        <div class="pad">
            <input type="hidden" name="action" value="dbkey" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="text" name="dbkey" class="inputtext" /> <br />

            <div class="center">
                <input type="submit" name="submit" value="Update Key" class="submit" />
            </div>
        </div>
    </form>
</div>

<?php
View::show_footer();
