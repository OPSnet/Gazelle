<?php

namespace Gazelle\File;

/* Note that there can be multiple rip logs per torrent, so there is one
 * torrent id and one or more log ids. In the usual course of operations,
 * an object is created with new File(<torrent-int>, <log-int>) which
 * points to one log (among possibly multiple) belonging to a torrent.
 *
 * A variant construction is allowed: new File(<int>, '*') which means
 * "all the log files". In this case, calling the remove() method removes
 * *all* the logs. You can of course remove just one log file if the log
 * id is specified in the constructor with new File(<int>, <int>).
 */

class RipLog extends \Gazelle\File {
    public function __construct(
        public readonly int $id,
        public readonly int|string $logId,
    ) {}

    public function flush(): static {
        return $this;
    }

    public function location(): string {
        return "";
    }

    public function link(): string {
        return "";
    }

    /**
     * Path of a rip log.
     */
    public function path(): string {
        $key = strrev(sprintf('%04d', $this->id));
        return sprintf("%s/%02d/%02d/{$this->id}_{$this->logId}.log",
            STORAGE_PATH_RIPLOG, substr($key, 0, 2), substr($key, 2, 2)
        );
    }

    /**
     * Move an existing rip log to the file storage location.
     */
    public function put(string $source): bool {
        // From a $_FILES post?
        if (is_uploaded_file($source)) {
            return move_uploaded_file($source, $this->path()) !== false;
        }
        return parent::put((string)file_get_contents($source));
    }

    /**
     * Remove one or more rip logs of a torrent
     */
    public function remove(): int {
        if ($this->exists()) {
             return (int)unlink($this->path());
        } elseif ($this->logId === '*') {
            $fileList = glob($this->path());
            if ($fileList === false) {
                return 0;
            }
            foreach ($fileList as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    if (!new self($this->id, (int)$match[1])->remove()) {
                        return 0;
                    }
                }
            }
            return 1;
        }
        return 0;
    }
}
