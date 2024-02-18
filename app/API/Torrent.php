<?php

namespace Gazelle\API;

class Torrent extends AbstractAPI {
    public function run() {
        switch ($_GET['req']) {
            case 'group':
                return $this->tgroup((int)($_GET['group_id'] ?? 0));
            default:
            case 'torrent':
                return $this->torrent((int)($_GET['torrent_id'] ?? 0));
        }
    }

    protected function torrent(int $id): array {
        $torrent = (new \Gazelle\Manager\Torrent())->findById($id);
        if (is_null($torrent)) {
            json_error('Torrent not found');
        }
        if (is_null($torrent)) {
            json_error('Torrent not found');
        }
        if (!$torrent->hasTGroup()) {
            json_error('Torrent has been orphaned');
        }
        $tgroup = $torrent->group();
        return [
            'ID'             => $torrent->id(),
            'Name'           => $tgroup->name(),
            'Year'           => $tgroup->year(),
            'ReleaseTypeID'  => $tgroup->releaseType(),
            'ReleaseType'    => $tgroup->releaseTypeName(),
            'Artists'        => $tgroup->artistRole()?->idList(),
            'DisplayArtists' => $tgroup->artistName(),
            'Media'          => $torrent->media(),
            'Format'         => $torrent->format(),
            'HasLog'         => $torrent->hasLog(),
            'HasLogDB'       => $torrent->hasLogDb(),
            'LogScore'       => $torrent->logScore(),
            'Snatched'       => $torrent->snatchTotal(),
            'Seeders'        => $torrent->seederTotal(),
            'Leechers'       => $torrent->leecherTotal(),
        ];
    }

    protected function tgroup(int $id): array {
        $tgroup = (new \Gazelle\Manager\TGroup())->findById($id);
        if (is_null($tgroup)) {
            json_error('Group not found');
        }
        return [
            'ID'             => $tgroup->id(),
            'Name'           => $tgroup->name(),
            'Year'           => $tgroup->year(),
            'ReleaseTypeID'  => $tgroup->releaseType(),
            'ReleaseType'    => $tgroup->releaseTypeName(),
            'Artists'        => $tgroup->artistRole()?->idList(),
            'DisplayArtists' => $tgroup->artistName(),
        ];
    }
}
