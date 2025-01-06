<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_clear_cache')) {
    error(403);
}

$flusher = new Gazelle\Util\CacheMultiFlush();

if (isset($_POST['confirm-flush']) && isset($_POST['global_flush'])) {
    authorize();
    $Cache->flush();
}

$flushed = false;
$multi   = false;
$result = [];
$begin = microtime(true);
if (!empty($_REQUEST['key'])) {
    $Keys = preg_split('/\s+/', trim($_REQUEST['key']));
    if ($Keys && isset($_REQUEST['flush']) && isset($_REQUEST['check'])) {
        $delete = $Cache->delete_multi($Keys);
        foreach ($delete as $key => $response) {
            $result[$key] = CACHE_RESPONSE[$response] ?? "retcode:$response";
        }
        $flushed = true;
    } elseif ($Keys && (isset($_REQUEST['view']) || isset($_REQUEST['json']))) {
        foreach ($Keys as $Key) {
            foreach (CACHE_PERMISSION as $name => $permission) {
                if (str_contains($Key, $name) && !$Viewer->permitted($permission)) {
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
        $shape = array_map(fn($s) => $definitions[$s],
            array_intersect(array_keys($_REQUEST), array_keys($definitions))
        );
        if (isset($_REQUEST["$namespace-free"]) && str_contains($_REQUEST["$namespace-free"], '*')) {
            $freeform = preg_split(
                '/\s+/',
                str_replace('*', '%d', (string)$_REQUEST["$namespace-free"])
            );
            if (is_array($freeform)) {
                $shape = array_merge($shape, $freeform);
            }
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

echo $Twig->render('admin/cache-management.twig', [
    'can_flush'     => $Viewer->permitted('admin_clear_cache'),
    'delta'         => $delta,
    'flushed'       => $flushed,
    'key'           => $_REQUEST['key'] ?? '',
    'multi'         => $multi,
    'result'        => $result,
]);
