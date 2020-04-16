<?php

namespace Gazelle\File;

class RipLog extends \Gazelle\File {
    const STORAGE = STORAGE_PATH_RIPLOG;
    const STORAGE_LEGACY = SERVER_ROOT_LIVE . '/logs';

    public function put($source, $id) {
        // PHASE 2: The following can be removed after validation
        $this->db->prepared_query('SELECT 1 FROM torrents_logs WHERE TorrentID = ? AND LogID = ?', $id[0], $id[1]);
        if (!$this->db->has_results()) {
            $this->db->prepared_query('
                INSERT INTO torrents_logs (TorrentID, LogID, Log) VALUES (?, ?, ?)
                ', $id[0], $id[1], $source
            );
        }
        copy($source, $this->path_legacy($id)); // PHASE 2: remove
        return false !== move_uploaded_file($source, $this->path($id));
    }

    public function remove($id) {
        $torrent_id = $id[0];
        $log_id = $id[1];
        if (is_null($log_id)) {
            $logfiles = glob($this->path($torrent_id, '*'));
            foreach ($logfiles as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    $log_id = $match[1];
                    $this->remove([$torrent_id, $log_id]);
                }
            }
        }
        $path = $this->path($id);
        if ($this->exists($id)) {
            unlink($path);
        }
        $path = $this->path_legacy($id);
        if (file_exists($path)) {
            unlink($path);
        }
        // PHASE 2 REMOVE
        $this->db->prepared_query('
            DELETE FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ', $torrent_id, $log_id
        );
        return $this->db->affected_rows(); // PHASE 2 return true
    }

    public function path($id) {
        $torrent_id = $id[0];
        $log_id = $id[1];
        $key = strrev(sprintf('%04d', $torrent_id));
        return sprintf('%s/%02d/%02d', self::STORAGE, substr($key, 0, 2), substr($key, 2, 2))
            . '/' . $torrent_id . '_' . $log_id . '.log';
    }

    public function pathLegacy($id) {
        return self::STORAGE_LEGACY . '/' . $id[0] . '_' . $id[1] . '.log';
    }

    public function get($id) {
        if (!$this->exists($id)) {
            $path = $this->path_legacy($id);
            if (file_exists($path)) {
                parent::put(file_get_contents($path), [$id[0], $id[1]]);
            }
        }
        return file_get_contents($this->path($id));
    }
}
