<?php

namespace Gazelle;

abstract class BaseUser extends BaseObject {
    public function __construct(
        protected User $user,
    ) {}

    public function user(): User {
        return $this->user;
    }
}
