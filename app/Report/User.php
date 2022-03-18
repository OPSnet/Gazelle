<?php

namespace Gazelle\Report;

class User extends AbstractReport {
    public function __construct(
        protected \Gazelle\User $subject
    ) { }

    public function template(): string {
        return 'report/user.twig';
    }
}
