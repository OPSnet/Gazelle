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
 *
 * In the commit this paragraph was added, the torrents_files
 * table and the torrents_logs.Log column were removed. This file
 * is left as-is, for historical purposes for people who want to
 * migrate existing Gazelle installations.
 */

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../classes/util.php');

$Cache = new Gazelle\Cache;
$DB    = new DB_MYSQL;
Gazelle\Base::initialize($Cache, $DB, Gazelle\Util\Twig::factory());
$Debug = new Gazelle\Debug($Cache, $DB);
$Debug->handle_errors();

$allConfig = [
    '-html' => [
        'CHECK' => 'SELECT Log FROM torrents_logs WHERE TorrentID = ? AND LogID = ?',
        'FILER' => new Gazelle\File\RipLogHTML,
        'MD5'   => 'SELECT Log AS digest FROM torrents_logs WHERE TorrentID = ? AND LogID = ?',
        'PIPE'  => '/usr/bin/find ' . STORAGE_PATH_RIPLOGHTML . ' -type f',
        'MATCH' => '~/(\d+)_(\d+)\.html$~',
        'NEWLN' => true,
    ],
    '-log' => [
        'CHECK' => 'SELECT 1 FROM torrents_logs WHERE TorrentID = ? AND LogID = ?',
        'FILER' => new Gazelle\File\RipLog,
        'MD5'   => null,
        'PIPE'  => '/usr/bin/find ' . STORAGE_PATH_RIPLOG . ' -type f',
        'MATCH' => '~/(\d+)_(\d+)\.log$~',
        'NEWLN' => false,
    ],
    '-torrent' => [
        'CHECK' => 'SELECT 1 FROM torrents WHERE ID = ?',
        'FILER' => new Gazelle\File\Torrent,
        'MD5'   => 'SELECT File AS digest FROM torrents_files WHERE TorrentID = ?',
        'PIPE'  => '/usr/bin/find ' . STORAGE_PATH_TORRENT . ' -type f',
        'MATCH' => '~/(\d+)\.torrent$~',
        'NEWLN' => false,
    ],
];

if ($argc < 2 || !isset($allConfig[$argv[1]])) {
    die('usage: ' . basename($argv[0]) . " <-html|-log|-torrent>\n");
}
$config = $allConfig[$argv[1]];

ini_set('max_execution_time', -1);

$find = popen($config['PIPE'], 'r');
if ($find === false) {
    die("Could not popen(" . $config['PIPE'] . ")\n");
}

$begin     = microtime(true);
$processed = 0;
$orphan    = 0;
$alien     = 0;
$mismatch  = 0;

while (($file = fgets($find)) !== false) {
    $file = trim($file);
    ++$processed;

    if (!preg_match($config['MATCH'], $file, $match)) {
        ++$alien;
        echo "$file is alien\n";
        continue;
    }

    if (!$DB->scalar($config['CHECK'], ...array_slice($match, 1))) {
        ++$orphan;
        echo "$file is orphan\n";
        continue;
    }

    if (is_null($config['MD5'])) {
        continue;
    }
    $db_digest = md5($DB->scalar($config['MD5'], ...array_slice($match, 1)) . ($config['NEWLN'] ? "\n" : ''));
    $file_digest = md5(file_get_contents($file));
    if ($db_digest != $file_digest) {
        echo "$file contents $file_digest does not match db $db_digest\n";
        ++$mismatch;
    }
}

$delta = microtime(true) - $begin;
printf("## Processed %d files in %0.1f seconds (%0.2f file/sec), %d orphan, %d alien, %d mismatch.\n",
    $processed, $delta, $delta > 0 ? $processed / $delta : 0, $orphan, $alien, $mismatch
);
