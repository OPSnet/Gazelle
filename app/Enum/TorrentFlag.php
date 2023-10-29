<?php

namespace Gazelle\Enum;

enum TorrentFlag: string {
    case badFile     = 'torrents_bad_files';
    case badFolder   = 'torrents_bad_folders';
    case badTag      = 'torrents_bad_tags';
    case cassette    = 'torrents_cassette_approved';
    case lossyMaster = 'torrents_lossymaster_approved';
    case lossyWeb    = 'torrents_lossyweb_approved';
    case noLineage   = 'torrents_missing_lineage';

    public function label(): string {
        return match ($this) {
            self::badFile     => 'Bad Files',
            self::badFolder   => 'Bad Folders',
            self::badTag      => 'Bad Tags',
            self::cassette    => 'Cassette Approved',
            self::lossyMaster => 'Lossy Master Approved',
            self::lossyWeb    => 'Lossy WEB Approved',
            self::noLineage   => 'Missing Lineage', /** @phpstan-ignore-line */
        };
    }
}
