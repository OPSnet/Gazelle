<?php

namespace Gazelle;

abstract class BaseUser extends Base {
    protected User $user;

    public function __construct(User $user) {
        parent::__construct();
        $this->user = $user;
    }
}
