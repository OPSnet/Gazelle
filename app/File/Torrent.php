<?php

namespace Gazelle\File;

class Torrent extends \Gazelle\File {
    /**
     * Path of a torrent file
     */
    public function path(mixed $id): string {
        $key = strrev(sprintf('%04d', $id));
        return sprintf('%s/%02d/%02d/%d.torrent',
            STORAGE_PATH_TORRENT, substr($key, 0, 2), substr($key, 2, 2), $id
        );
    }
}
