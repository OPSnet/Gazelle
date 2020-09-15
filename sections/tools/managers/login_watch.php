<?php

use Gazelle\Util\SortableTableHeader;

if (!(check_perms('admin_login_watch') || check_perms('admin_manage_ipbans'))) {
    error(403);
}

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
if (isset($ban)) {
    $nrBan = $watch->setBan(
        $LoggedUser['ID'],
        $_REQUEST['reason'] ?? "Banned by {$LoggedUser['Username']} from login watch.",
        $ban
    );
}
if (isset($clear)) {
    $nrClear = $watch->setClear($clear);
}

$sortOrderMap = [
    'ipaddr'       => ['inet_aton(w.IP)', 'asc'],
    'user'         => ['coalesce(um.username, w.capture)', 'asc'],
    'last_attempt' => ['w.LastAttempt', 'desc'],
    'banned_until' => ['w.BannedUntil', 'desc'],
    'attempts'     => ['w.Attempts',    'desc'],
    'bans'         => ['w.Bans',        'desc'],
];
$sortOrder = (!empty($_GET['order']) && isset($sortOrderMap[$_GET['order']])) ? $_GET['order'] : 'last_attempt';
$orderBy = $sortOrderMap[$sortOrder][0];
$orderWay = (empty($_GET['sort']) || $_GET['sort'] == $sortOrderMap[$sortOrder][1])
    ? $sortOrderMap[$sortOrder][1]
    : SortableTableHeader::SORT_DIRS[$sortOrderMap[$sortOrder][1]];
$headerInfo = new SortableTableHeader([
    'ipaddr'       => 'IP',
    'user'         => 'User',
    'attempts'     => 'Attempts',
    'bans'         => 'Bans',
    'last_attempt' => 'Last Attempt',
    'banned_until' => 'Login Forbidden',
], $sortOrder, $orderWay);

$header = [];
foreach (array_keys($sortOrderMap) as $column) {
    $header[$column] = $headerInfo->emit($column, $sortOrderMap[$column][1]);
}

$list = $watch->activeList($orderBy, $orderWay);
$resolve = isset($_REQUEST['resolve']);
foreach ($list as &$attempt) {
    $attempt['dns'] = $resolve ? gethostbyaddr($attempt['ipaddr']) : $attempt['ipaddr'];
    if ($attempt['banned']) {
        $attempt['ipaddr'] = sprintf('<span title="Banned">%s&nbsp;%s</span>', $attempt['ipaddr'], "\xE2\x9B\x94");
    }
}
unset($attempt);

View::show_header('Login Watch');
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
        'header'   => $header,
        'list'     => $list,
        'can_ban'  => check_perms('admin_manage_ipbans'),
        'nr_ban'   => $nrBan ?? null,
        'nr_clear' => $nrClear ?? null,
        'resolve'  => $resolve,
    ]) ?>
</div>
<?php
View::show_footer();
