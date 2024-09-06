<?php

namespace Gazelle\Json\Ajax;

use Gazelle\Intf\CollageEntry;

class CollageAdd extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Collage         $collage,
        protected CollageEntry             $entry,
        protected \Gazelle\User            $user,
        protected \Gazelle\Manager\Collage $manager,
    ) {}

    protected function setFailure(string $message): array {
        $this->failure($message);
        return [];
    }

    public function payload(): array {
        if (!$this->user->permitted('site_collages_delete')) {
            if ($this->collage->isLocked()) {
                return $this->setFailure('locked');
            }
            if ($this->collage->isPersonal() && !$this->collage->isOwner($this->user)) {
                return $this->setFailure('personal');
            }
            if ($this->collage->maxGroups() > 0 && $this->collage->numEntries() >= $this->collage->maxGroups()) {
                return $this->setFailure('max entries reached');
            }
            $maxGroupsPerUser = $this->collage->maxGroupsPerUser();
            if ($maxGroupsPerUser > 0) {
                if ($this->collage->contributionTotal($this->user) >= $maxGroupsPerUser) {
                    return $this->setFailure('you have already contributed');
                }
            }
        }

        if (!$this->collage->addEntry($this->entry, $this->user)) {
            return $this->setFailure('already present?');
        }

        if ($this->collage->isArtist()) {
            $this->manager->flushDefaultArtist($this->user);
        } else {
            $this->manager->flushDefaultGroup($this->user);
        }
        return ['link' => $this->collage->link()];
    }
}
