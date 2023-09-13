<?php

namespace Gazelle;

abstract class BaseUser extends BaseObject {
    public function __construct(
        protected User $user,
    ) {}

    /**
     * The id() of a BaseUser is the id() of the underlying BaseObject
     */
    public function id(): int {
        return $this->user->id();
    }

    /**
     * A reference implementation for BaseObject::link().
     * Derived BaseUser may redefine this if it makes sense.
     */
    public function link(): string {
        return $this->user->link();
    }

    /**
     * A reference implementation for BaseObject::location().
     * Derived BaseUser may redefine this if it makes sense.
     */
    public function location(): string {
        return $this->user->location();
    }

    /**
     * The underlying User object. All public methods are available and
     * the BaseUser object can modify() it as necessary.
     */
    public function user(): User {
        return $this->user;
    }
}
