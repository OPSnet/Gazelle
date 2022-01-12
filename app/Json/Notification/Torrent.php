<?php

namespace Gazelle\Json\Notification;

class Torrent extends \Gazelle\Json {
    protected \Gazelle\User\Notification\Torrent  $notifier;
    protected \Gazelle\Util\Paginator             $paginator;
    protected \Gazelle\Manager\Torrent            $torMan;

    public function setNotifier(\Gazelle\User\Notification\Torrent $notifier) {
        $this->notifier = $notifier;
        return $this;
    }

    public function setPaginator(\Gazelle\Util\Paginator $paginator) {
        $this->paginator = $paginator;
        return $this;
    }

    public function setTorrentManager(\Gazelle\Manager\Torrent $torMan) {
        $this->torMan = $torMan;
        return $this;
    }

    public function torrentPayload(array $info): array {
        $torrent = $info['torrent'];
        $tgroup = $torrent->group();
        return [
            'torrentId'       => $torrent->id(),
            'groupId'         => $tgroup->id(),
            'groupName'       => html_entity_decode($tgroup->name()),
            'groupCategoryId' => $tgroup->categoryId(),
            'wikiImage'       => $tgroup->image(),
            'torrentTags'     => implode(' ', $tgroup->tagNameList()),
            'size'            => $torrent->size(),
            'fileCount'       => $torrent->fileTotal(),
            'media'           => $torrent->media(),
            'format'          => $torrent->format(),
            'encoding'        => $torrent->encoding(),
            'scene'           => $torrent->isScene(),
            'groupYear'       => $tgroup->year(),
            'remasterYear'    => $torrent->remasterYear(),
            'remastered'      => $torrent->isRemastered(),
            'remasterTitle'   => $torrent->remasterTitle() ?? '',
            'remasterRecordLabel'
                              => $torrent->remasterRecordLabel() ?? '',
            'remasterCatalogueNumber'
                              => $torrent->remasterCatalogueNumber() ?? '',
            'snatched'        => $torrent->snatchTotal(),
            'seeders'         => $torrent->seederTotal(),
            'leechers'        => $torrent->leecherTotal(),
            'hasLog'          => $torrent->hasLog(),
            'hasCue'          => $torrent->hasCue(),
            'logScore'        => $torrent->logScore(),
            'logChecksum'     => $torrent->logChecksum(),
            'logCount'        => count($torrent->ripLogIdList()),
            'ripLogIds'       => $torrent->ripLogIdList(),
            'freeTorrent'     => $torrent->isFreeleech(),
            'isNeutralLeech'  => $torrent->isFreeleech(),
            'reported'        => $this->torMan->hasReport($this->notifier->user(), $torrent->id()),
            'time'            => $torrent->uploadDate(),
            'description'     => $torrent->description(),
            'filter'          => $info['filter_name'],
        ];
    }

    public function payload(): ?array {
        if (!isset($this->notifier)) {
            $this->failure('notifier not set');
            return null;
        }
        if (!isset($this->paginator)) {
            $this->failure('paginator not set');
            return null;
        }
        if (!isset($this->torMan)) {
            $this->failure('torrent manager not set');
            return null;
        }

        $this->paginator->setTotal($this->notifier->total());

        return [
            'currentPage' => $this->paginator->page(),
            'pages'       => $this->paginator->pages(),
            'numNew'      => $this->notifier->unread(),
            'results'     => array_map(
                fn($t) => $this->torrentPayload($t),
                $this->notifier->page(
                    $this->torMan,
                    $this->paginator->limit(),
                    $this->paginator->offset(),
                )
            ),
        ];
    }
}
