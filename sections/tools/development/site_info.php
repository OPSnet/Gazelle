<?php

if ((!defined('DEBUG_MODE') || DEBUG_MODE !== true) && !check_perms('admin_site_debug')) {
    error(403);
}

$info = new Gazelle\SiteInfo;

function uid ($id) {
    return sprintf("%s(%d)", posix_getpwuid($id)['name'], $id);
}

function gid ($id) {
    return sprintf("%s(%d)", posix_getgrgid($id)['name'], $id);
}

$random = openssl_random_pseudo_bytes(8, $strong);

View::show_header('Site Information');

echo G::$Twig->render('admin/site-info.twig', [
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
]);

View::show_footer();
