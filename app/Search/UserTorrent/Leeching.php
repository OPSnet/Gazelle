<?php

declare(strict_types=1);

namespace Gazelle\Search\UserTorrent;

use Gazelle\User;

class Leeching extends AbstractSearch {
    public function join(): string {
        return 'inner join relay.xbt_files_users xfu on (xfu.fid = t."ID")';
    }

    public function orderColumn(): string {
        return 'xfu.mtime - xfu.timespent';
    }

    public function setUser(User $user, User $viewer): static {
        if (!$user->propertyVisible($viewer, 'leeching')) {
            $this->cond[] = 'false';
        } else {
            $this->cond[] = "xfu.active = 1 and xfu.remaining > 0 and xfu.uid = {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $user->id;
        }
        return $this;
    }
}
