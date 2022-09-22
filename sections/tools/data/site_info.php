<?php

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
}

function uid ($id) {
    return sprintf("%s(%d)", posix_getpwuid($id)['name'], $id);
}

function gid ($id) {
    return sprintf("%s(%d)", posix_getgrgid($id)['name'], $id);
}

$info = new Gazelle\SiteInfo;

if (isset($_GET['mode']) && $_GET['mode'] === 'userrank') {
    $config = new Gazelle\UserRank\Configuration(RANKING_WEIGHT);
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
    echo $Twig->render('admin/site-info.twig', [
        'uid'              => uid(posix_getuid()),
        'gid'              => gid(posix_getgid()),
        'euid'             => uid(posix_geteuid()),
        'egid'             => uid(posix_getegid()),
        'openssl_strong'   => $strong,
        'uptime'           => $info->uptime(),
        'timestamp_php'    => date('Y-m-d H:i:s'),
        'timestamp_db'     => $DB->scalar("SELECT now()"),
        'phpinfo'          => $info->phpinfo(),
        'php_version'      => phpversion(),
        'git_branch'       => $info->gitBranch(),
        'git_hash'         => $info->gitHash(),
        'git_hash_remote'  => $info->gitHashRemote(),
        'composer_version' => $info->composerVersion(),
        'package'          => $info->composerPackages(),
        'phinx'            => $info->phinx(),
        'mysql_version'    => $DB->scalar('SELECT @@version'),
        'no_pk'            => $info->tablesWithoutPK(),
    ]);
}
