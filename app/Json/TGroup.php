<?php

namespace Gazelle\Json;

class TGroup extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\TGroup $tgroup,
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\Torrent $torMan = new \Gazelle\Manager\Torrent(),
    ) {}

    public function tgroupPayload(): array {
        $tgroup = $this->tgroup;
        $musicInfo = static::artistPayload($tgroup);

        return [
            'wikiBody'        => \Text::full_format($tgroup->description()),
            'wikiBBcode'      => $tgroup->description(),
            'wikiImage'       => $tgroup->image(),
            'proxyImage'      => image_cache_encode($tgroup->image()),
            'id'              => $tgroup->id,
            'name'            => $tgroup->name(),
            'year'            => $tgroup->year(),
            'recordLabel'     => $tgroup->recordLabel() ?? '',
            'catalogueNumber' => $tgroup->catalogueNumber() ?? '',
            'releaseType'     => $tgroup->releaseType() ?? '',
            'releaseTypeName' => $tgroup->releaseTypeName(),
            'categoryId'      => $tgroup->categoryId(),
            'categoryName'    => $tgroup->categoryName(),
            'time'            => $tgroup->time(),
            'vanityHouse'     => $tgroup->isShowcase(),
            'isBookmarked'    => new \Gazelle\User\Bookmark($this->user)->isBookmarked($tgroup),
            'tags'            => array_values($tgroup->tagNameList()),
            'musicInfo'       => $musicInfo,
        ];
    }

    public function payload(): array {
        return [
            'group' => $this->tgroupPayload(),
            'torrents' => array_reduce($this->tgroup->torrentIdList(), function ($acc, $id) {
                $torrent = $this->torMan->findById($id);
                if ($torrent) {
                    $acc[] = new Torrent($torrent, $this->user, $this->torMan)->torrentPayload();
                }
                return $acc;
            }, []),
        ];
    }
}
