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
        ];
        return $this;
    }
}
