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
}
