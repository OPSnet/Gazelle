<?php

namespace Gazelle;

abstract class BaseUser extends BaseObject {
    public function __construct(
        protected User $user,
    ) {}

    public function id(): int {
        return $this->user->id();
    }

    public function user(): User {
        return $this->user;
    }
}
