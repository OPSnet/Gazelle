<?php

namespace Gazelle\Report;

class User extends AbstractReport {
    public function __construct(
        protected int $reportId,
        protected \Gazelle\User $subject
    ) { }

    public function template(): string {
        return 'report/user.twig';
    }

    public function bbLink(): string {
        return "the user [user]{$this->subject->username()}[/user]";
    }

    public function title(): string {
        return 'User Report: ' . display_str($this->subject->username());
    }
}
