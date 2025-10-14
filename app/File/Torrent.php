<?php

namespace Gazelle\File;

class Torrent extends \Gazelle\File {
    public function __construct(
        public readonly int $id,
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
     * Path of a torrent file
     */
    public function path(): string {
        $key = strrev(sprintf('%04d', $this->id));
        return sprintf('%s/%02s/%02s/%d.torrent',
            STORAGE_PATH_TORRENT, substr($key, 0, 2), substr($key, 2, 2), $this->id
        );
    }
}
