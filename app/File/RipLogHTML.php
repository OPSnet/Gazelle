<?php

namespace Gazelle\File;

class RipLogHTML extends \Gazelle\File {
    /**
     * Remove one or more HTML-ized rip logs of a torrent
     *
     * @param mixed $id The unique identifier [torrentId, logId] of the object
     *                  If logId is null, all logs are taken into consideration
     */
    public function remove(mixed $id): bool {
        [$torrentId, $logId] = $id;
        if (is_null($logId)) {
            $htmlfiles = glob($this->path([$torrentId, '*']));
            foreach ($htmlfiles as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    $logId = $match[1];
                    $this->remove([$torrentId, $logId]);
                }
            }
            return true;
        } else {
            $path = $this->path($id);
            if (file_exists($path)) {
                 return @unlink($path);
            }
            return false;
        }
    }

    /**
     * Path of a HTML-ized rip log.
     */
    public function path(mixed $id): string {
        [$torrentId, $logId] = $id;
        $key = strrev(sprintf('%04d', $torrentId));
        return sprintf("%s/%02d/%02d/{$torrentId}_{$logId}.html",
            STORAGE_PATH_RIPLOGHTML, substr($key, 0, 2), substr($key, 2, 2)
        );
    }
}
