<?php

namespace Gazelle\Report;

class Comment extends AbstractReport {
    public function __construct(
        protected \Gazelle\Comment\AbstractComment $subject
    ) { }

    public function template(): string {
        return 'report/comment.twig';
    }

    public function setContext(string $title) {
        $this->context['title'] = $title;
        return $this;
    }

    public function bbLink(): string {
        return "[url={$this->subject->url()}]this comment[/url]";
    }

    public function title(): string {
        return "Comment Report: #{$this->subject->id()} " . shortenString($this->subject->body(), 50);
    }
}
