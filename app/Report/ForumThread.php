<?php

namespace Gazelle\Report;

class ForumThread extends AbstractReport {

    public function __construct(
        protected \Gazelle\ForumThread $subject
    ) { }

    public function template(): string {
        return 'report/forum-thread.twig';
    }

    public function bbLink(): string {
        return "the forum thread [url={$this->subject->id()}]" . display_str($this->title()) . '[/url]';
    }

    public function title(): string {
        return 'Forum Thread Report: ' . display_str($this->title());
    }
}
