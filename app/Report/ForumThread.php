<?php

namespace Gazelle\Report;

class ForumThread extends AbstractReport {
    public function __construct(
        protected int $reportId,
        protected \Gazelle\ForumThread $subject,
    ) {}

    public function template(): string {
        return 'report/forum-thread.twig';
    }

    public function bbLink(): string {
        return "the forum thread [thread]{$this->subject->id()}[/thread]";
    }

    public function titlePrefix(): string {
        return 'Forum Thread Report: ';
    }

    public function title(): string {
        return $this->subject->title();
    }
}
