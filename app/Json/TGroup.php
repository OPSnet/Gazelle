<?php

namespace Gazelle\Json;

class TGroup extends \Gazelle\Json {
    protected \Gazelle\TGroup $tgroup;
    protected \Gazelle\User $user;

    public function setTGroup(\Gazelle\TGroup $tgroup) {
        $this->tgroup = $tgroup;
        return $this;
    }

    public function setViewer(\Gazelle\User $user) {
        $this->user = $user;
        return $this;
    }

    public function tgroupPayload(): array {
        $tgroup = $this->tgroup;
        if ($tgroup->categoryId() != 1) {
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

    public function payload(): ?array {
        if (!isset($this->tgroup)) {
            $this->failure('group not set');
            return null;
        }
        if (!isset($this->user)) {
            $this->failure('viewer not set');
            return null;
        }

        $ids     =  $this->tgroup->torrentIdList();
        $details = [];
        $torMan  = (new \Gazelle\Manager\Torrent)->setViewer($this->user);

        foreach ($ids as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if ($torrent) {
                $details[] = (new Torrent)
                    ->setTorrent($torrent)
                    ->setViewer($this->user)
                    ->torrentPayload();
            }
        }

        return [
            'group' => $this->tgroupPayload(),
            'torrents' => $details,
        ];
    }
}

