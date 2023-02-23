<?php

/* The torrents_logs.Log column no longer exists as of this commit.
 * This script is left for historical purposes and for people who
 * want to migrate an existing Gazelle installation.
 * Similarly, the Gazelle\File\RipLog::pathLegacy() method no
 * longer exists.
 */

require_once(__DIR__ . '/../lib/bootstrap.php');

ini_set('max_execution_time', -1);

define('CHUNK', 100);

$offset    = 0;
$processed = 0;
$newLog    = 0;
$errLog    = 0;
$newHtml   = 0;
$errHtml   = 0;

$db        = Gazelle\DB::DB();
$logFiler  = new Gazelle\File\RipLog;
$htmlFiler = new Gazelle\File\RipLogHTML;

while (true) {
    $db->prepared_query('
        SELECT LogID, TorrentID, Log
        FROM torrents_logs
        WHERE LogID > ?
        ORDER BY LogID
        LIMIT ?
        ', $offset, CHUNK
    );
    if (!$db->has_results()) {
        break;
    }

    while (list($logId, $torrentId, $log) = $db->next_record(MYSQLI_NUM, false)) {
        $last = $logId;
        ++$processed;
        if (file_exists($logFiler->pathLegacy([$torrentId, $logId])) && !file_exists($logFiler->path([$torrentId, $logId]))) {
            if (!copy($logFiler->pathLegacy([$torrentId, $logId]), $logFiler->path([$torrentId, $logId]))) {
                ++$errLog;
            }
            ++$newLog;
        }
        if (!file_exists($htmlFiler->path([$torrentId, $logId]))) {
            if (!$htmlFiler->put($log, [$torrentId, $logId])) {
                ++$errHtml;
            }
            $htmlFiler->put($log . "\n", [$torrentId, $logId]);
            ++$newHtml;
        }
    }

    printf("begin %7d end %7d processed %7d / log %7d error %7d / html %7d error %7d\n",
        $offset, $last, $processed, $newLog, $errLog, $newHtml, $errHtml);
    $offset = $last;
}
