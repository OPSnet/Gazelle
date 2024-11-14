<?php

namespace Gazelle;

use Gazelle\Enum\TorrentFlag;

class TorrentDeleted extends TorrentAbstract {
    final public const tableName = 'deleted_torrents';
    final public const CACHE_KEY = 'tdel_%d';

    public function location(): string {
        return "log.php?search=Torrent+" . $this->id;
    }

    public function infoRow(): ?array {
        $info = self::$db->rowAssoc("
            SELECT t.GroupID,
                t.UserID,
                t.Media,
                t.Format,
                t.Encoding,
                t.Remastered,
                t.RemasterYear,
                t.RemasterTitle,
                t.RemasterCatalogueNumber,
                t.RemasterRecordLabel,
                t.Scene,
                t.HasLog,
                t.HasCue,
                t.HasLogDB,
                t.LogScore,
                t.LogChecksum,
                t.info_hash,
                t.FileCount,
                t.FileList,
                t.FilePath,
                t.Size,
                t.FreeTorrent,
                t.FreeLeechType,
                t.created,
                t.Description,
                t.LastReseedRequest,
                0             AS Seeders,
                0             AS Leechers,
                0             AS Snatched,
                NULL          AS last_action,
                ''            AS ripLogIds
            FROM deleted_torrents t
            WHERE t.ID = ?
            GROUP BY t.ID
            ", $this->id
        );
        if ($info) {
            self::$db->prepared_query("
                SELECT a.Name
                FROM torrent_attr a JOIN deleted_torrent_has_attr ha ON (a.ID = ha.TorrentAttrID)
                WHERE ha.TorrentID = ?
            ", $this->id);
            $info['attr'] = [];
            foreach (self::$db->to_array(escape: false) as $row) {
                $info['attr'][$row['Name']] = true;
            }
        }
        return $info;
    }

    public function isDeleted(): bool {
        return true;
    }
}
