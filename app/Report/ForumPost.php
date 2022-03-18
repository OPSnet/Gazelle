<?php

namespace Gazelle\Report;

class ForumPost extends AbstractReport {

    public function __construct(
        protected \Gazelle\Forum $subject
    ) { }

    public function template(): string {
        return 'report/forum-post.twig';
    }

    public function setContext(int $postId) {
        $postInfo = $this->subject->postInfo($postId);
        $this->context = [
            'author_id' => $postInfo['user-id'],
            'body'      => $postInfo['body'],
        ];
        return $this;
    }
}
