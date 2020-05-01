<?php

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');
require_once(__DIR__.'/../classes/util.php');

$Debug = new DEBUG;
$Debug->handle_errors();

$DB = new DB_MYSQL;
$Cache = new CACHE($MemcachedServers);

define('CHUNK', 100);

$offset    = 0;
$processed = 0;
$newLog    = 0;
$newHtml   = 0;

$logFiler = new \Gazelle\File\RipLog($DB, $Cache);
$htmlFiler = new \Gazelle\File\RipLogHTML($DB, $Cache);

while (true) {
    $DB->prepared_query('
        SELECT LogID, TorrentID, Log, Details
        FROM torrents_logs
        WHERE LogID > ?
        ORDER BY LogID
        LIMIT ?
        ', $offset, CHUNK
    );
    if (!$DB->has_results()) {
        break;
    }

    $list = $DB->to_array();
    foreach ($list as $torrent) {
        list($logId, $torrentId, $log, $dtails) = $torrent;
        $last = $logId;
        ++$processed;
        if (!file_exists($logFiler->path([$torrentId, $logId]))) {
            copy($logFiler->pathLegacy([$torrentId, $logId]), $logFiler->path([$torrentId, $logId]));
            ++$newLog;
        }
        if (!file_exists($htmlFiler->path([$torrentId, $logId]))) {
            $htmlFiler->put($log, [$torrentId, $logId]);
            ++$newHtml;
        }
    }

    printf("begin %7d end %7d processed %7d log %7d html %7d\n", $offset, $last, $processed, $newLog, $newHtml);
    $offset = $last;
}
