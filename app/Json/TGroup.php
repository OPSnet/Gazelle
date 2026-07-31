<?php

declare(strict_types=1);

namespace Gazelle\Json;

use Gazelle\Manager\Torrent as TorrentManager;
use Gazelle\User;
use Gazelle\User\Bookmark;

class TGroup extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\TGroup $tgroup,
        protected User            $user,
        protected TorrentManager  $torMan = new TorrentManager(),
    ) {}

    public function tgroupPayload(): array {
        $tgroup    = $this->tgroup;
        $musicInfo = static::artistPayload($tgroup);

        return [
            'wikiBody'        => \Text::full_format($tgroup->description()),
            'wikiBBcode'      => $tgroup->description(),
            'wikiImage'       => $tgroup->image(),
            'proxyImage'      => image_cache_encode($tgroup->image()),
            'id'              => $tgroup->id,
            'name'            => $tgroup->name(),
            'year'            => $tgroup->year(),
            'releaseType'     => $tgroup->releaseType() ?? '',
            'releaseTypeName' => $tgroup->releaseTypeName(),
            'categoryId'      => $tgroup->categoryId(),
            'categoryName'    => $tgroup->categoryName(),
            'time'            => $tgroup->created(),
            'vanityHouse'     => $tgroup->isShowcase(),
            'isBookmarked'    => new Bookmark($this->user)->isBookmarked($tgroup),
            'tags'            => array_values($tgroup->tagNameList()),
            'musicInfo'       => $musicInfo,
        ];
    }

    public function payload(): array {
        return [
            'group' => $this->tgroupPayload(),
            'torrents' => array_reduce(
                $this->tgroup->torrentIdList(),
                function ($acc, $id) {
                    $torrent = $this->torMan->findById($id);
                    if ($torrent) {
                        $acc[] = new Torrent($torrent, $this->user, $this->torMan)
                            ->torrentPayload();
                    }
                    return $acc;
                },
                [],
            ),
        ];
    }
}
