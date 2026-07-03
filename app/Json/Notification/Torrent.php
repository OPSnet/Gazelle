<?php

namespace Gazelle\Json\Notification;

use Gazelle\Manager\Riplog            as RiplogManager;
use Gazelle\Manager\Torrent           as TorrentManager;
use Gazelle\User\Notification\Torrent as UserNotifier;
use Gazelle\Util\Paginator;

class Torrent extends \Gazelle\Json {
    public function __construct(
        protected UserNotifier   $notifier,
        protected Paginator      $paginator,
        protected TorrentManager $torMan = new TorrentManager(),
        protected RiplogManager  $riplogManager = new RiplogManager(),
    ) {}

    public function torrentPayload(array $info): array {
        $torrent      = $info['torrent'];
        $tgroup       = $torrent->group();
        $riplogIdList = $this->riplogManager->fetchIdList($torrent);
        return [
            'torrentId'       => $torrent->id,
            'groupId'         => $tgroup->id,
            'groupName'       => $tgroup->name(),
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
            'logCount'        => count($riplogIdList),
            'ripLogIds'       => $riplogIdList,
            'freeTorrent'     => $torrent->isFreeleech(),
            'isNeutralLeech'  => $torrent->isNeutralleech(),
            'reported'        => (bool)$torrent->reportTotal($this->notifier->user()),
            'time'            => $torrent->created(),
            'description'     => $torrent->description(),
            'filter'          => $info['filter_name'],
        ];
    }

    public function payload(): array {
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
