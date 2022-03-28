<?php

namespace Gazelle\Report;

class ForumThread extends AbstractReport {

    public function __construct(
        protected \Gazelle\Forum $subject
    ) { }

    public function template(): string {
        return 'report/forum-thread.twig';
    }

    public function setContext(int $threadId) {
        $threadInfo = $this->subject->threadInfo($threadId);
        $this->context = [
            'author_id' => $threadInfo['AuthorID'],
            'title'     => $threadInfo['Title'],
            'thread_id' => $threadId,
        ];
        return $this;
    }

    public function bbLink(): string {
        return "the forum thread [url="
            . $this->subject->threadUrl($this->context['thread_id'])
            . ']' . display_str($this->context['title']) . '[/url]';
    }

    public function title(): string {
        return 'Forum Thread Report: ' . display_str($this->context['title']);
    }
}
