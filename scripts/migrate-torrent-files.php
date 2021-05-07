<?php

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../classes/util.php');

$Cache = new Gazelle\Cache;
$DB    = new DB_MYSQL;
Gazelle\Base::initialize($Cache, $DB, Gazelle\Util\Twig::factory());
$Debug = new Gazelle\Debug($Cache, $DB);
$Debug->handle_errors();

ini_set('max_execution_time', -1);

define('CHUNK', 100);

$offset    = 0;
$processed = 0;
$new       = 0;

$filer = new \Gazelle\File\Torrent;

while (true) {
    $DB->prepared_query('
        SELECT TorrentID, File
        FROM torrents_files
        WHERE TorrentID > ?
        ORDER BY TorrentID
        LIMIT ?
        ', $offset, CHUNK
    );
    if (!$DB->has_results()) {
        break;
    }

    $last = $offset;
    $list = $DB->to_array(false, MYSQLI_NUM, false);
    foreach ($list as $torrent) {
        list($id, $file) = $torrent;
        $last = $id;
        ++$processed;
        if (file_exists($filer->path($id))) {
            continue;
        }
        $filer->put($file, $id);
        ++$new;
    }

    printf("begin %7d end %7d processed %7d new %7d\n", $offset, $last, $processed, $new);
    $offset = $last;
}
