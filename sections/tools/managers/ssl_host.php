<?php

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$ssl = new Gazelle\Manager\SSLHost;

$remove = array_map(
    fn ($n) => explode('-', $n)[1],
    array_keys(
        array_filter($_POST, fn ($e) => preg_match('/^id-\d+$/', $e), ARRAY_FILTER_USE_KEY)
    )
);

if ($remove) {
    $ssl->removeList($remove);
}
if (!empty($_POST['hostname']) && !empty($_POST['port'])) {
    authorize();
    $ssl->add(trim($_POST['hostname']), (int)$_POST['port']);
}

echo $Twig->render('admin/ssl_host.twig', [
    'list'   => $ssl->list(),
    'viewer' => $Viewer,
]);
