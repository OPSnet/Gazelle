<?php

namespace Gazelle\Report;

class ForumPost extends AbstractReport {
    public function __construct(
        protected int $reportId,
        protected \Gazelle\ForumPost $subject
    ) { }

    public function template(): string {
        return 'report/forum-post.twig';
    }

    public function bbLink(): string {
        return "[thread]{$this->subject->thread()->id()}:{$this->subject->id()}[/thread]";
    }

    public function titlePrefix(): string {
        return "Forum Post Report: ";
    }

    public function title(): string {
        return "Post ID #{$this->subject->id()}";
    }
}
