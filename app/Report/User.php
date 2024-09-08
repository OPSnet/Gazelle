<?php

namespace Gazelle\Report;

class User extends AbstractReport {
    public function __construct(
        protected readonly int $reportId,
        protected readonly \Gazelle\User $subject,
    ) { }

    public function template(): string {
        return 'report/user.twig';
    }

    public function bbLink(): string {
        return "the user [user]{$this->subject->username()}[/user]";
    }

    public function titlePrefix(): string {
        return 'User Report: ';
    }

    public function title(): string {
        return $this->subject->username();
    }
}
