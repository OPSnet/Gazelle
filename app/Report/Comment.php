<?php

namespace Gazelle\Report;

class Comment extends AbstractReport {
    public function __construct(
        protected int $reportId,
        protected \Gazelle\Comment\AbstractComment $subject
    ) { }

    public function template(): string {
        return 'report/comment.twig';
    }

    public function setContext(string $title): static {
        $this->context['title'] = $title;
        return $this;
    }

    public function bbLink(): string {
        return "[url={$this->subject->url()}]this comment[/url]";
    }

    public function titlePrefix(): string {
        return "Comment Report: #{$this->subject->id()} ";
    }

    public function title(): string {
        return shortenString($this->subject->body(), 50);
    }
}
