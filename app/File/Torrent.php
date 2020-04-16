<?php

namespace Gazelle\File;

class Torrent extends \Gazelle\File {
    const STORAGE = STORAGE_PATH_TORRENT;

    public function get($id) {
        $path = $this->path($id);
        if (!file_exists($path)) {
            $this->db->prepared_query('SELECT File FROM torrents_files WHERE TorrentID = ?', $id);
            if (!$this->db->has_results()) {
                return null;
            }
            list($file) = $this->db->next_record(MYSQLI_NUM, false);
            $this->put($file, $id);
            $path = $this->path($id);
        }
        return file_get_contents($path);
    }

    public function put($source, $id) {
        return file_put_contents($this->path($id), $source);
    }

    public function path($id) {
        $key = strrev(sprintf('%04d', $id));
        $k1 = substr($key, 0, 2);
        $k2 = substr($key, 2, 2);
        return sprintf('%s/%02d/%02d/%d.torrent', self::STORAGE, $k1, $k2, $id);
    }
}
