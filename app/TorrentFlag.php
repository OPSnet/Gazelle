<?php

namespace Gazelle;

enum TorrentFlag: string {
    case badFile     = 'torrents_bad_files';
    case badFolder   = 'torrents_bad_folders';
    case badTag      = 'torrents_bad_tags';
    case cassette    = 'torrents_cassette_approved';
    case lossyMaster = 'torrents_lossymaster_approved';
    case lossyWeb    = 'torrents_lossyweb_approved';
    case noLineage   = 'torrents_missing_lineage';

    public function label(): string {
        return match($this) {
            TorrentFlag::badFile     => 'Bad Files',
            TorrentFlag::badFolder   => 'Bad Folders',
            TorrentFlag::badTag      => 'Bad Tags',
            TorrentFlag::cassette    => 'Cassette Approved',
            TorrentFlag::lossyMaster => 'Lossy Master Approved',
            TorrentFlag::lossyWeb    => 'Lossy WEB Approved',
            TorrentFlag::noLineage   => 'Missing Lineage', /** @phpstan-ignore-line */
        };
    }
}
