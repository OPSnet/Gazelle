<?php

namespace Gazelle\File;

class RipLog extends \Gazelle\File {
    const STORAGE = STORAGE_PATH_RIPLOG;
    const STORAGE_LEGACY = SERVER_ROOT_LIVE . '/logs';

    /**
     * Move an existing rip log to the file storage location.
     * NOTE: This is a change in behaviour from the parent class,
     *      which is expecting the file contents.
     *
     * @param string $source Path to the file, usually the result
     *      of a POST operation.
     * @param array $id The unique identifier [torrentId, logId] of the object
     */
    public function put(string $source, /* array */ $id): bool {
        return false !== move_uploaded_file($source, $this->path($id));
    }

    /**
     * Remove one or more rip logs of a torrent
     *
     * @param array $id The unique identifier [torrentId, logId] of the object
     *                  If logId is null, all logs are taken into consideration
     * @return bool true (TODO: record individual unlink successes in the case of a wildcard
     */
    public function remove(/* array */ $id): bool {
        $torrent_id = $id[0];
        $log_id = $id[1];
        if (is_null($log_id)) {
            $logfiles = glob($this->path([$torrent_id, '*']));
            foreach ($logfiles as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    $log_id = $match[1];
                    $this->remove([$torrent_id, $log_id]);
                }
            }
        } else {
            $path = $this->path($id);
            if ($this->exists($id)) {
                @unlink($path);
            }
        }
        return true;
    }

    /**
     * Path of a rip log.
     *
     * @param array $id rip log identifier [torrentId, logId]
     */
    public function path(/* array */ $id): string {
        $torrent_id = $id[0];
        $log_id = $id[1];
        $key = strrev(sprintf('%04d', $torrent_id));
        return sprintf('%s/%02d/%02d', self::STORAGE, substr($key, 0, 2), substr($key, 2, 2))
            . '/' . $torrent_id . '_' . $log_id . '.log';
    }
}
