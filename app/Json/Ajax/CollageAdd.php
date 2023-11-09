<?php

namespace Gazelle\Json\Ajax;

class CollageAdd extends \Gazelle\Json {
    public function __construct(
        protected int                      $collageId,
        protected int                      $entryId,
        protected string                   $name,
        protected \Gazelle\User            $user,
        protected \Gazelle\Manager\Collage $manager,
        protected \Gazelle\Manager\Artist  $artistManager,
        protected \Gazelle\Manager\TGroup  $tgroupManager,
    ) {}

    protected function setFailure(string $message): array {
        $this->failure($message);
        return [];
    }

    public function payload(): array {
        $collage = $this->manager->findById($this->collageId);
        if (is_null($collage)) {
            if (preg_match(COLLAGE_REGEXP, $this->name, $match)) {
                // Looks like a URL
                $collage = $this->manager->findById((int)$match['id']);
            }
            if (is_null($collage)) {
                // Must be a name of a collage
                $collage = $this->manager->findByName($this->name);
            }
            if (is_null($collage)) {
                return $this->setFailure("collage not found");
            }
        }

        $entryManager = $collage->isArtist() ? $this->artistManager : $this->tgroupManager;
        $entry = $entryManager->findById($this->entryId);
        if (is_null($entry)) {
            return $this->setFailure('entry not found');
        }

        if (!$this->user->permitted('site_collages_delete')) {
            if ($collage->isLocked()) {
                return $this->setFailure('locked');
            }
            if ($collage->isPersonal() && !$collage->isOwner($this->user->id())) {
                return $this->setFailure('personal');
            }
            if ($collage->maxGroups() > 0 && $collage->numEntries() >= $collage->maxGroups()) {
                return $this->setFailure('max entries reached');
            }
            $maxGroupsPerUser = $collage->maxGroupsPerUser();
            if ($maxGroupsPerUser > 0) {
                if ($collage->countByUser($this->user->id()) >= $maxGroupsPerUser) {
                    return $this->setFailure('you have already contributed');
                }
            }
        }

        if (!$collage->addEntry($entry->id(), $this->user->id())) {
            return $this->setFailure('already present?');
        }

        if ($collage->isArtist()) {
            $this->manager->flushDefaultArtist($this->user->id());
        } else {
            $this->manager->flushDefaultGroup($this->user->id());
        }
        return ['link' => $collage->link()];
    }
}
