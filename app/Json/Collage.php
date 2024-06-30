<?php

namespace Gazelle\Json;

class Collage extends \Gazelle\Json {
    protected static int $ENTRIES_PER_PAGE = 25;
    protected int $version = 3;

    public function __construct(
        protected \Gazelle\Collage         $collage,
        protected int                      $page,
        protected \Gazelle\User            $user,
        protected \Gazelle\Manager\TGroup  $tgMan,
        protected \Gazelle\Manager\Torrent $torMan,
    ) {}

    public function artistPayload(): array {
        return $this->collage->nameList();
    }

    public function tgroupPayload(): array {
        $entryList = array_slice(
            $this->collage->entryList(),
            ($this->page - 1) * static::$ENTRIES_PER_PAGE,
            static::$ENTRIES_PER_PAGE
        );
        $payload = [];
        foreach ($entryList as $tgroupId) {
            $tgroup = $this->tgMan->findById($tgroupId);
            if (is_null($tgroup)) {
                continue;
            }
            $tgJson = new \Gazelle\Json\TGroup($tgroup, $this->user, $this->torMan);
            $payload[] = $tgJson->payload();
        }
        return $payload;
    }

    public function payload(): array {
        $entryList = $this->collage->entryList();
        return array_merge(
            [
                'id'                  => $this->collage->id(),
                'name'                => $this->collage->name(),
                'description'         => \Text::full_format($this->collage->description()),
                'description_raw'     => $this->collage->description(),
                'creatorID'           => $this->collage->ownerId(),
                'deleted'             => $this->collage->isDeleted(),
                'collageCategoryID'   => $this->collage->categoryId(),
                'collageCategoryName' => COLLAGE[$this->collage->categoryId()],
                'locked'              => $this->collage->isLocked(),
                'maxGroups'           => $this->collage->maxGroups(),
                'maxGroupsPerUser'    => $this->collage->maxGroupsPerUser(),
                'hasBookmarked'       => (new \Gazelle\User\Bookmark($this->user))->isCollageBookmarked($this->collage->id()),
                'subscriberCount'     => $this->collage->numSubscribers(),
                'torrentGroupIDList'  => $entryList,
                'pages'               => $this->collage->isArtist() ? 1 : ceil(count($entryList) / static::$ENTRIES_PER_PAGE),
            ],
            $this->collage->isArtist()
                ? ['artists'       => $this->artistPayload()]
                : ['torrentgroups' => $this->tgroupPayload()]
        );
    }
}
