<?php

declare(strict_types=1);

namespace Gazelle\Search\UserTorrent;

use Gazelle\User;

class Downloaded extends AbstractSearch {
    public function join(): string {
        return 'inner join user_download ud on (ud.id_torrent = t."ID")';
    }

    public function orderColumn(): string {
        return 'ud.created';
    }

    public function setUser(User $user, User $viewer): static {
        if (!$user->propertyVisible($viewer, 'downloaded')) {
            $this->cond[] = 'false';
        } else {
            $this->cond[] = "ud.id_user = {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $user->id;
        }
        return $this;
    }
}
