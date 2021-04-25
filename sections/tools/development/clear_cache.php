<?php
if (!check_perms('users_mod') || !check_perms('admin_clear_cache')) {
    error(403);
}

$flusher = new Gazelle\Util\CacheMultiFlush;

$flushed = false;
$multi   = false;
$result = [];
$begin = microtime(true);
if (!empty($_REQUEST['key'])) {
    $Keys = preg_split('/\s+/', trim($_REQUEST['key']));
    if (isset($_REQUEST['flush']) && ($_REQUEST['check'] ?? '') === 'on') {
        if (!check_perms('admin_clear_cache')) {
            error(403);
        }
        $delete = $Cache->deleteMulti($Keys);
        foreach ($delete as $key => $response) {
            $result[$key] = CACHE_RESPONSE[$response] ?? "retcode:$response";
        }
        $flushed = true;
    } elseif (isset($_REQUEST['view']) || isset($_REQUEST['json'])) {
        foreach ($Keys as $Key) {
            foreach (CACHE_PERMISSION as $name => $permission) {
                if (strpos($Key, $name) !== false && !check_perms($permission)) {
                    error(403);
                }
            }
            $result[$Key] = $Cache->get_value($Key);
        }
    }
} else {
    foreach (array_keys(CACHE_DB) as $namespace) {
        if (empty($_REQUEST["flush-$namespace"])) {
            continue;
        }
        $definitions = CACHE_NAMESPACE[$namespace];
        $shape = array_map(function ($s) use ($definitions) { return $definitions[$s];},
            array_intersect(array_keys($_REQUEST), array_keys($definitions))
        );
        if (isset($_REQUEST["$namespace-free"]) && strpos($_REQUEST["$namespace-free"], '*') !== false) {
            $shape = array_merge($shape, preg_split('/\s+/', str_replace('*', '%d', $_REQUEST["$namespace-free"])));
        }
        $result = [$namespace => $flusher->multiFlush($namespace, $shape)];
        $flushed = true;
        $multi   = true;
    }
}
$delta = microtime(true) - $begin;

if (isset($_REQUEST['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (count($result) == 1) {
        print(json_encode(current($result)));
    } else {
        print(json_encode($result));
    }
    exit;
}

View::show_header('Cache Inspector');

echo $Twig->render('admin/cache-management.twig', [
    'can_flush'     => check_perms('admin_clear_cache'),
    'delta'         => $delta,
    'flushed'       => $flushed,
    'key'           => $_REQUEST['key'] ?? '',
    'multi'         => $multi,
    'namespace'     => CACHE_NAMESPACE,
    'result'        => $result,
]);
View::show_footer();
