<?php

namespace Gazelle\File;

class RipLogHTML extends \Gazelle\File {
    const STORAGE = STORAGE_PATH_RIPLOGHTML;

    public function get($id) {
        $path = $this->path($id);
        if (!file_exists($path)) {
            $torrentId = $id[0];
            $logId = $id[1];
            $save = $this->db->get_query_id();
            $this->db->prepared_query('
                SELECT Log FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
                ', $torrentId, $logId
            );
            if (!$this->db->has_results()) {
                return null;
            }
            list($file) = $this->db->next_record();
            $this->put($file, $id);
            $this->db->set_query_id($save);
            // PHASE 2: DELETE FROM torrents_logs
        }
        return file_get_contents($path);
    }

    public function put($source, $id) {
        $out = fopen($this->path($id), 'wb');
        fwrite($out, $source);
        fclose($out);
        return true;
    }

    public function remove($id) {
        $torrentId = $id[0];
        $logId = $id[1];
        if (is_null($logId)) {
            $htmlfiles = glob($this->path($torrentId, '*'));
            foreach ($htmlfiles as $path) {
                if (preg_match('/(\d+)\.log/', $path, $match)) {
                    $logId = $match[1];
                    $this->remove([$torrentId, $logId]);
                }
            }
        }
        $path = $this->path($id);
        if (file_exists($path)) {
            unlink($path);
        }
        // PHASE 3: remove this
        $this->db->prepared_query('
            DELETE FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ', $torrentId, $logId
        );
        // PHASE 2 end

        return true;
    }

    public function path($id) {
        $torrentId = $id[0];
        $logId = $id[1];
        $key = strrev(sprintf('%04d', $torrentId));
        return sprintf('%s/%02d/%02d', self::STORAGE, substr($key, 0, 2), substr($key, 2, 2))
            . '/' . $torrentId . '_' . $logId . '.html';
    }
}
