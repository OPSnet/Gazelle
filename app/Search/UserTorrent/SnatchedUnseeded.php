<?php

declare(strict_types=1);

namespace Gazelle\Search\UserTorrent;

use Gazelle\User;

class SnatchedUnseeded extends AbstractSearch {
    public function join(): string {
        return 'inner join relay.xbt_snatched xs on (xs.fid = t."ID") left join relay.xbt_files_users xfu using (uid, fid)';
    }

    public function orderColumn(): string {
        return 'xs.tstamp';
    }

    public function setUser(User $user, User $viewer): static {
        if (!$user->propertyVisible($viewer, 'snatched')) {
            $this->cond[] = 'false';
        } else {
            $this->cond[] = "xfu.fid is null and xs.uid = {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $user->id;
        }
        return $this;
    }
}
