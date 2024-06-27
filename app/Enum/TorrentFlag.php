<?php

namespace Gazelle\Enum;

enum TorrentFlag: string {
    case badTag      = 'bad_tags';
    case badFolder   = 'bad_folders';
    case badFile     = 'bad_files';
    case noLineage   = 'missing_lineage';
    case trumpable   = 'trumpable';
    case cassette    = 'cassette_approved';
    case lossyMaster = 'lossymaster_approved';
    case lossyWeb    = 'lossyweb_approved';

    public function label(): string {
        return match ($this) {
            self::badFile     => 'Bad Files',
            self::badFolder   => 'Bad Folders',
            self::badTag      => 'Bad Tags',
            self::noLineage   => 'Missing Lineage',
            self::trumpable   => 'Trumpable',
            self::cassette    => 'Cassette Approved',
            self::lossyMaster => 'Lossy Master Approved',
            self::lossyWeb    => 'Lossy WEB Approved', /* @phpstan-ignore-line */
        };
    }

    public function permission(): string {
        return match ($this) {
            self::noLineage   => 'site_edit_lineage',
            default           => 'users_mod',
        };
    }

    public function description(): string {
        return match ($this) {
            self::badFile     => 'The torrent has bad file names.',
            self::badFolder   => 'The torrent has bad folder names.',
            self::badTag      => 'The torrent has bad tags.',
            self::noLineage   => 'The torrent is missing lineage information.',
            self::trumpable   => 'The torrent is trumpable for miscellaneous reasons.',
            self::cassette    => 'This is an approved cassette rip.',
            self::lossyMaster => 'This is an approved lossy master.',
            self::lossyWeb    => 'This is an approved Lossy WEB.',  /* @phpstan-ignore-line */
        };
    }

    public function labelClass(): string {
        return match ($this) {
            self::badFile,
            self::badFolder,
            self::badTag,
            self::noLineage,
            self::trumpable => 'reported',
            self::cassette,
            self::lossyMaster,
            self::lossyWeb  => 'approved',  /* @phpstan-ignore-line */
        };
    }
}
