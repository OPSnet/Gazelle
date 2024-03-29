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

    public function title(): string {
        return "Forum Post Report: Post ID #{$this->subject->id()}";
    }
}
