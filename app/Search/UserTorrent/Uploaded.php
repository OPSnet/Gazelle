<?php

declare(strict_types=1);

namespace Gazelle\Search\UserTorrent;

use Gazelle\User;

class Uploaded extends AbstractSearch {
    public function join(): string {
        return '';
    }

    public function orderColumn(): string {
        return 't.created';
    }

    public function setUser(User $user, User $viewer): static {
        if (!$user->propertyVisible($viewer, 'uploads')) {
            $this->cond[] = 'false';
        } else {
            $this->cond[] = 't."UserID" = '
                . $this->pg()->placeholder(count($this->args));
            $this->args[] = $user->id;
        }
        return $this;
    }
}
