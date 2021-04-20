<?php

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

$watch = new Gazelle\LoginWatch('0.0.0.0');
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

$headerInfo = new \Gazelle\Util\SortableTableHeader('last_attempt', [
    'ipaddr'       => ['dbColumn' => 'inet_aton(w.IP)', 'defaultSort' => 'asc',  'text' => 'IP'],
    'user'         => ['dbColumn' => 'coalesce(um.username, w.capture)', 'defaultSort' => 'asc', 'text' => 'User'],
    'attempts'     => ['dbColumn' => 'w.Attempts',      'defaultSort' => 'desc', 'text' => 'Attempts'],
    'bans'         => ['dbColumn' => 'w.Bans',          'defaultSort' => 'desc', 'text' => 'Bans'],
    'last_attempt' => ['dbColumn' => 'w.LastAttempt',   'defaultSort' => 'desc', 'text' => 'Last Attempt'],
    'banned_until' => ['dbColumn' => 'w.BannedUntil',   'defaultSort' => 'desc', 'text' => 'Login Forbidden'],
]);

$header = [];
foreach ($headerInfo->getAllSortKeys() as $column) {
    $header[$column] = $headerInfo->emit($column);
}

$paginator = new Gazelle\Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 0));
$paginator->setTotal($watch->activeTotal());

$list = $watch->activeList($headerInfo->getOrderBy(), $headerInfo->getOrderDir(), $paginator->limit(), $paginator->offset());
$resolve = isset($_REQUEST['resolve']);
foreach ($list as &$attempt) {
    $attempt['dns'] = $resolve ? gethostbyaddr($attempt['ipaddr']) : $attempt['ipaddr'];
    if ($attempt['banned']) {
        $attempt['ipaddr'] = sprintf('<span title="Banned">%s&nbsp;%s</span>', $attempt['ipaddr'], "\xE2\x9B\x94");
    }
}
unset($attempt);

View::show_header('Login Watch');
echo G::$Twig->render('admin/login-watch.twig', [
    'auth'      => $LoggedUser['AuthKey'],
    'header'    => $header,
    'list'      => $list,
    'can_ban'   => check_perms('admin_manage_ipbans'),
    'nr_ban'    => $nrBan ?? null,
    'nr_clear'  => $nrClear ?? null,
    'paginator' => $paginator,
    'resolve'   => $resolve,
]);
View::show_footer();
