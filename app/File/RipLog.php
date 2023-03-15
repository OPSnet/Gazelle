<?php

namespace Gazelle\File;

class RipLog extends \Gazelle\File {
    /**
     * Move an existing rip log to the file storage location.
     * NOTE: This is a change in behaviour from the parent class,
     *      which is expecting the file contents.
     *
     * $source Path to the file, usually the result of a POST operation.
     * $id The unique identifier [torrentId, logId] of the object
     */
    public function put(string $source, mixed $id): bool {
        return false !== move_uploaded_file($source, $this->path($id));
    }

    /**
     * Remove one or more rip logs of a torrent
     *
     * $id The unique identifier [torrentId, logId] of the object
     *     If logId is null, all logs are taken into consideration
     */
    public function remove(mixed $id): bool {
        [$torrentId, $logId] = $id;
        if (is_null($logId)) {
            $logfiles = glob($this->path([$torrentId, '*']));
            foreach ($logfiles as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    $logId = $match[1];
                    $this->remove([$torrentId, $logId]);
                }
            }
            return true;
        } else {
            if ($this->exists($id)) {
                return @unlink($this->path($id));
            }
            return false;
        }
    }

    /**
     * Path of a rip log.
     */
    public function path(mixed $id): string {
        [$torrentId, $logId] = $id;
        $key = strrev(sprintf('%04d', $torrentId));
        return sprintf("%s/%02d/%02d/{$torrentId}_{$logId}.log",
            STORAGE_PATH_RIPLOG, substr($key, 0, 2), substr($key, 2, 2)
        );
    }
}
