<?php
if (!(check_perms('admin_login_watch') || check_perms('admin_manage_ipbans'))) {
    error(403);
}
View::show_header('Login Watch');

if ($_POST) {
    authorize();
    if (check_perms('admin_manage_ipbans')) {
        $ban = [];
        $clear = [];
        $admin = array_filter($_POST, function ($x) { return preg_match('/^admin-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
        foreach ($admin as $admin_id => $op) {
            $id = (int)explode('-', $admin_id)[1];
            if ($op == 'ban') {
                $ban[] = $id;
            } elseif ($op == 'clear') {
                $clear[] = $id;
            }
        }
    } elseif (check_perms('admin_login_watch')) {
        $clear = [];
        $admin = array_filter($_POST, function ($x) { return preg_match('/^clear-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
        foreach ($admin as $admin_id => $op) {
            $id = (int)explode('-', $admin_id)[1];
            $clear[] = $id;
        }
    }
}

$watch = new Gazelle\LoginWatch;
if ($ban) {
    $nrBan = $watch->setBan(
        $LoggedUser['ID'],
        $_REQUEST['reason'] ?? "Banned by {$LoggedUser['Username']} from login watch.",
        $ban
    );
}
if ($clear) {
    $nrClear = $watch->setClear($clear);
}

$list = $watch->activeList();
$resolve = isset($_REQUEST['resolve']);
foreach ($list as &$attempt) {
    $attempt['dns'] = $resolve ? gethostbyaddr($attempt['ipaddr']) : $attempt['ipaddr'];
}
unset($attempt);

?>
<div class="thin">
    <div class="header">
        <h2>Login Watch Management</h2>
    </div>
    <div class="linkbox">
        <a href="tools.php?action=ip_ban">IP Address Bans</a>
    </div>
    <?= G::$Twig->render('admin/login-watch.twig', [
        'auth'     => $LoggedUser['AuthKey'],
        'list'     => $list,
        'can_ban'  => check_perms('admin_manage_ipbans'),
        'nr_ban'   => $nrBan ?? null,
        'nr_clear' => $nrClear ?? null,
        'resolve'  => $resolve,
    ]) ?>
</div>
<?php
View::show_footer();
