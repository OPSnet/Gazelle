<?php

namespace Gazelle;

use Gazelle\Enum\TorrentFlag;

class TorrentDeleted extends TorrentAbstract {
    final const tableName = 'deleted_torrents';
    final const CACHE_KEY = 'tdel_%d';

    public function location(): string { return "log.php?search=Torrent+" . $this->id; }

    public function infoRow(): ?array {
        return self::$db->rowAssoc("
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
                hex(t.info_hash) AS info_hash,
                t.info_hash      AS info_hash_raw,
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
                tbt.TorrentID AS BadTags,
                tbf.TorrentID AS BadFolders,
                tfi.TorrentID AS BadFiles,
                mli.TorrentID AS MissingLineage,
                cas.TorrentID AS CassetteApproved,
                lma.TorrentID AS LossymasterApproved,
                lwa.TorrentID AS LossywebApproved,
                ''            AS ripLogIds
            FROM deleted_torrents t
            LEFT JOIN deleted_torrents_bad_tags             AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_folders          AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_files            AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_missing_lineage      AS mli ON (mli.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_cassette_approved    AS cas ON (cas.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossyweb_approved    AS lwa ON (lwa.TorrentID = t.ID)
            WHERE t.ID = ?
            GROUP BY t.ID
            ", $this->id
        );
    }

    public function hasToken(int $userId): bool {
        return false;
    }

    public function isDeleted(): bool {
        return true;
    }

    public function addFlag(TorrentFlag $flag, User $user): int {
        return 0;
    }
}
