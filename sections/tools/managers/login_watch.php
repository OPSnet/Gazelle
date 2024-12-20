<?php

if (!$Viewer->permittedAny('admin_login_watch', 'admin_manage_ipbans')) {
    error(403);
}

if ($_POST) {
    authorize();
    $canBan = $Viewer->permitted('admin_manage_ipbans');
    $ban    = [];
    $clear  = [];
    foreach (array_key_filter_and_map('admin-', $_POST) as $id => $val) {
        if ($canBan && $val === 'ban') {
            $ban[] = $id;
        } elseif ($val === 'clear') {
            $clear[] = $id;
        }
    }
}

$watch = new Gazelle\LoginWatch('0.0.0.0');
if (isset($ban)) {
    $nrBan = $watch->setBan(
        $Viewer,
        $_REQUEST['reason'] ?? "Banned by " . $Viewer->username() . " from login watch.",
        $ban,
        new Gazelle\Manager\IPv4(),
    );
}
if (isset($clear)) {
    $nrClear = $watch->setClear($clear);
}

$headerInfo = new Gazelle\Util\SortableTableHeader('last_attempt', [
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

$paginator = new Gazelle\Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($watch->activeTotal());

$list = $watch->activeList($headerInfo->getOrderBy(), $headerInfo->getOrderDir(), $paginator->limit(), $paginator->offset());
$resolve = isset($_REQUEST['resolve']);

echo $Twig->render('admin/login-watch.twig', [
    'auth'      => $Viewer->auth(),
    'header'    => $header,
    'list'      => $list,
    'can_ban'   => $Viewer->permitted('admin_manage_ipbans'),
    'nr_ban'    => $nrBan ?? null,
    'nr_clear'  => $nrClear ?? null,
    'paginator' => $paginator,
    'resolve'   => $resolve,
]);
