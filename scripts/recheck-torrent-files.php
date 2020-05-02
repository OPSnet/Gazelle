<?php

/* Scan all the torrent storage directory and look for things that
 * don't belong, and orphaned files of torrents that have been
 * deleted (via moderation or catastrophe).
 * Once it has been determined that all files reported do need to
 * be removed, the output can be piped as follows:
 *
 *   ... | awk '$1 != "##" {print $1}' | xargs rm
 *
 * and these extranenous files will be unlinked.
 */

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');
require_once(__DIR__.'/../classes/util.php');

$Debug = new DEBUG;
$Debug->handle_errors();

ini_set('max_execution_time', -1);

$DB = new DB_MYSQL;

$processed = 0;
$orphan    = 0;
$alien     = 0;

$find = popen('/usr/bin/find ' . STORAGE_PATH_TORRENT . ' -type f', 'r');
if ($find === false) {
    die("Could not popen(find)\n");
}

$filer = new \Gazelle\File\Torrent($DB, new CACHE($MemcachedServers));
$begin = microtime(true);

while (($file = fgets($find)) !== false) {
    $file = trim($file);
    ++$processed;

    if (!preg_match('~/(\d+)\.torrent$~', $file, $match)) {
        ++$alien;
        echo "$file is alien\n";
        continue;
    }
    
    if (!$DB->scalar('SELECT ID FROM torrents WHERE ID = ?', $match[1])) {
        ++$orphan;
        echo "$file is orphan\n";
        continue;
    }
}

$delta = microtime(true) - $begin;
printf("## Processed %d files in %0.1f seconds (%0.2f file/sec), %d orphans, %d aliens.\n",
    $processed, $delta, $delta > 0 ? $processed / $delta : 0, $orphan, $alien
);
