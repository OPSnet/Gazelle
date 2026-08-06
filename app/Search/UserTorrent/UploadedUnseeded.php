<?php

declare(strict_types=1);

namespace Gazelle\Search\UserTorrent;

use Gazelle\User;

class UploadedUnseeded extends AbstractSearch {
    public function join(): string {
        return 'left join relay.xbt_files_users xfu on (xfu.fid = t."ID" and xfu.uid = t."UserID")';
    }

    public function orderColumn(): string {
        return 't.created';
    }

    public function setUser(User $user, User $viewer): static {
        if (!$user->propertyVisible($viewer, 'uploads')) {
            $this->cond[] = 'false';
        } else {
            $this->cond[] = 'xfu.fid is null and t."UserID" = '
                . $this->pg()->placeholder(count($this->args));
            $this->args[] = $user->id;
        }
        return $this;
    }
}
