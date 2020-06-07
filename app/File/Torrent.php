<?php

namespace Gazelle\File;

class Torrent extends \Gazelle\File {
    const STORAGE = STORAGE_PATH_TORRENT;

    /**
     * Path of a torrent file
     *
     * @param int id of torrent
     * @return Fully qualified filename
     */
    public function path(/* array */ $id) {
        $key = strrev(sprintf('%04d', $id));
        $k1 = substr($key, 0, 2);
        $k2 = substr($key, 2, 2);
        return sprintf('%s/%02d/%02d/%d.torrent', self::STORAGE, $k1, $k2, $id);
    }
}
