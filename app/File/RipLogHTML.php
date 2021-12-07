<?php

namespace Gazelle\File;

class RipLogHTML extends \Gazelle\File {
    const STORAGE = STORAGE_PATH_RIPLOGHTML;

    /**
     * Remove one or more HTML-ized rip logs of a torrent
     *
     * @param array $id The unique identifier [torrentId, logId] of the object
     *                  If logId is null, all logs are taken into consideration
     * @return bool True (TODO: record individual unlink successes in the case of a wildcard
     */
    public function remove(/* array */ $id) {
        $torrentId = $id[0];
        $logId = $id[1];
        if (is_null($logId)) {
            $htmlfiles = glob($this->path([$torrentId, '*']));
            foreach ($htmlfiles as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    $logId = $match[1];
                    $this->remove([$torrentId, $logId]);
                }
            }
        } else {
            $path = $this->path($id);
            if (file_exists($path)) {
                 @unlink($path);
            }
        }
        return true;
    }

    /**
     * Path of a HTML-ized rip log.
     *
     * @param array $id rip log identifier [torrentId, logId]
     */
    public function path(/* array */ $id): string {
        $torrentId = $id[0];
        $logId = $id[1];
        $key = strrev(sprintf('%04d', $torrentId));
        return sprintf('%s/%02d/%02d', self::STORAGE, substr($key, 0, 2), substr($key, 2, 2))
            . '/' . $torrentId . '_' . $logId . '.html';
    }
}
