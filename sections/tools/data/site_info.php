<?php

/**
 * phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_site_debug')) {
    Error403::error();
}

function uid(int $id): string {
    $uid = posix_getpwuid($id);
    return sprintf("%s(%d)", $uid === false ? '???' : $uid['name'], $id);
}

function gid(int $id): string {
    $gid = posix_getgrgid($id);
    return sprintf("%s(%d)", $gid === false ? '???' : $gid['name'], $id);
}

if (isset($_GET['mode']) && $_GET['mode'] === 'userrank') {
    $config = new UserRank\Configuration(RANKING_WEIGHT);
    $names = array_keys(RANKING_WEIGHT);
    $rankTable = [];
    foreach ($names as $name) {
        $rankTable[$name] = array_fill(0, 100, null);
        $instance = $config->instance($name)->build();
        foreach ($instance as $metric => $rank) {
            $rankTable[$name][$rank] = $metric;
        }
    }
    echo $Twig->render('admin/site-info-userrank.twig', [
        'name' => $names,
        'table' => $rankTable,
    ]);
} else {
    $random = openssl_random_pseudo_bytes(8, $strong);
    $db = new DB();
    $pg = new DB\Pg(PG_RO_DSN);
    echo $Twig->render('admin/site-info.twig', [
        'uid'              => uid(posix_getuid()),
        'gid'              => gid(posix_getgid()),
        'euid'             => uid(posix_geteuid()),
        'egid'             => gid(posix_getegid()),
        'openssl_strong'   => $strong,
        'mysql_version'    => $db->version(),
        'index' => [
            'redundant' => $db->redundantIndexList(),
            'unused'    => $db->unusedIndexList(),
        ],
        'pg_checkpoint'    => $pg->checkpointInfo(),
        'pg_version'       => $pg->version(),
        'php_version'      => phpversion(),
        'site_info'        => new SiteInfo(),
        'timestamp_php'    => date('Y-m-d H:i:s'),
        'timestamp_db'     => $db->now(),
    ]);
}
