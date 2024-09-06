<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

require_once(__DIR__ . '/../lib/bootstrap.php');

ini_set('max_execution_time', -1);

define('CHUNK', 100);

$db        = Gazelle\DB::DB();
$offset    = 0;
$processed = 0;
$new       = 0;

$filer = new Gazelle\File\Torrent();

while (true) {
    $db->prepared_query('
        SELECT TorrentID, File
        FROM torrents_files
        WHERE TorrentID > ?
        ORDER BY TorrentID
        LIMIT ?
        ', $offset, CHUNK
    );
    if (!$db->has_results()) {
        break;
    }

    $last = $offset;
    $list = $db->to_array(false, MYSQLI_NUM, false);
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
