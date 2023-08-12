<?php

namespace Gazelle\Torrent;

class Deleted extends \Gazelle\Base {
    /**
     * Get the metadata of a deleted torrent
     * If the torrent is not in the deleted_* tables, a fake array will returned
     *
     * @return array of many things
     */
    static public function info(int $torrentId): array {
        $template = "SELECT t.GroupID, t.UserID, t.Media, t.Format, t.Encoding,
                t.Remastered, t.RemasterYear, t.RemasterTitle, t.RemasterCatalogueNumber, t.RemasterRecordLabel,
                t.Scene, t.HasLog, t.HasCue, t.HasLogDB, t.LogScore, t.LogChecksum,
                hex(t.info_hash) as info_hash, t.info_hash as info_hash_raw,
                t.FileCount, t.FileList, t.FilePath, t.Size,
                t.FreeTorrent, t.FreeLeechType, t.created, t.Description, t.LastReseedRequest,
                0 AS Seeders, 0 AS Leechers, 0 AS Snatched, '2000-01-01 00:00:00' AS last_action,
                tbt.TorrentID AS BadTags, tbf.TorrentID AS BadFolders, tfi.TorrentID AS BadFiles, ml.TorrentID  AS MissingLineage,
                ca.TorrentID  AS CassetteApproved, lma.TorrentID AS LossymasterApproved, lwa.TorrentID AS LossywebApproved,
                NULL as ripLogIds, false AS PersonalFL, false AS IsSnatched, 0 AS LogCount
            FROM deleted_torrents t
            LEFT JOIN torrents_group tg ON (tg.ID = t.GroupID)
            LEFT JOIN deleted_torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
            WHERE t.ID = ?
        ";
        return self::$db->rowAssoc($template, $torrentId) ?? [
            'BadFiles'                => false,
            'BadFolders'              => false,
            'BadTags'                 => false,
            'CassetteApproved'        => false,
            'Description'             => '-inexistent torrent-',
            'Encoding'                => '',
            'FileCount'               => 0,
            'FileList'                => [],
            'FilePath'                => '',
            'Format'                  => '',
            'FreeLeechType'           => 0,
            'FreeTorrent'             => false,
            'GroupID'                 => 0,
            'HasCue'                  => false,
            'HasLog'                  => false,
            'HasLogDB'                => false,
            'IsSnatched'              => false,
            'LastReseedRequest'       => null,
            'Leechers'                => 0,
            'LogChecksum'             => false,
            'LogCount'                => 0,
            'LogScore'                => 0,
            'LossymasterApproved'     => false,
            'LossywebApproved'        => false,
            'Media'                   => '',
            'MissingLineage'          => false,
            'PersonalFL'              => false,
            'RemasterCatalogueNumber' => null,
            'RemasterRecordLabel'     => null,
            'RemasterTitle'           => null,
            'RemasterYear'            => null,
            'Remastered'              => false,
            'Reported'                => false,
            'Scene'                   => false,
            'Seeders'                 => 0,
            'Size'                    => 0,
            'Snatched'                => 0,
            'created'                 => null,
            'UserID'                  => 0,
            'info_hash'               => '',
            'info_hash_raw'           => '',
            'last_action'             => null,
            'ripLogIds'               => [],
        ];
    }
}
