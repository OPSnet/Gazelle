<?php

namespace Gazelle\Json;

class TGroup extends \Gazelle\Json {

    public function __construct(
        protected \Gazelle\TGroup $tgroup,
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\Torrent $torMan,
    ) { }

    public function tgroupPayload(): array {
        $tgroup = $this->tgroup;
        if ($tgroup->categoryName() != 'Music') {
            $musicInfo = null;
        } else {
            $role = $tgroup->artistRole()->idList();
            $musicInfo = [
                'artists'   => $role[1] ?? [],
                'with'      => $role[2] ?? [],
                'remixedBy' => $role[3] ?? [],
                'composers' => $role[4] ?? [],
                'conductor' => $role[5] ?? [],
                'dj'        => $role[6] ?? [],
                'producer'  => $role[7] ?? [],
                'arranger'  => $role[8] ?? [],
            ];
        }

        return [
            'wikiBody'        => \Text::full_format($tgroup->description()),
            'wikiBBcode'      => $tgroup->description(),
            'wikiImage'       => $tgroup->image(),
            'id'              => $tgroup->id(),
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
            'isBookmarked'    => (new \Gazelle\User\Bookmark($this->user))->isTorrentBookmarked($tgroup->id()),
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
                    $acc[] = (new Torrent($torrent, $this->user, $this->torMan))->torrentPayload();
                }
                return $acc;
            }, []),
        ];
    }
}
