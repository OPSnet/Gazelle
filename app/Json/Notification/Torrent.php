<?php

namespace Gazelle\Json\Notification;

class Torrent extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\User\Notification\Torrent $notifier,
        protected \Gazelle\Util\Paginator            $paginator,
        protected \Gazelle\Manager\Torrent           $torMan,
    ) {}

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
            'reported'        => $torrent->hasReport($this->notifier->user()),
            'time'            => $torrent->created(),
            'description'     => $torrent->description(),
            'filter'          => $info['filter_name'],
        ];
    }

    public function payload(): ?array {
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
