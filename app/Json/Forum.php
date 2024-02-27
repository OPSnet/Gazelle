<?php

namespace Gazelle\Json;

class Forum extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Forum               $forum,
        protected \Gazelle\User                $user,
        protected \Gazelle\Manager\ForumThread $threadMan,
        protected \Gazelle\Manager\User        $userMan,
        protected int                          $perPage,
        protected int                          $page,
    ) {}

    public function payload(): array {
        $lastRead  = $this->user->forumLastReadList($this->perPage, $this->forum);
        $list      = [];
        $userCache = [];
        foreach ($this->forum->threadPage($this->threadMan, $this->page) as $thread) {
            // handle read/unread posts - the reason we can't cache the whole page
            $unread = (!$thread->isLocked() || $thread->isPinned)
                && (
                    (empty($lastRead[$thread->id()]) || $lastRead[$thread->id()]['post_id'] < $thread->lastPostId())
                    && strtotime($thread->lastPostTime()) > $this->user->forumCatchupEpoch()
                );

            if (!isset($userCache[$thread->authorId()])) {
                $userCache[$thread->authorId()] = $this->userMan->findById($thread->authorId());
            }
            $author = $userCache[$thread->authorId()];
            if (!isset($userCache[$thread->lastAuthorId()])) {
                $userCache[$thread->lastAuthorId()] = $this->userMan->findById($thread->lastAuthorId());
            }
            $lastAuthor = $userCache[$thread->lastAuthorId()];

            $list[] = [
                'topicId'        => $thread->id(),
                'title'          => $thread->title(),
                'authorId'       => $thread->authorId(),
                'authorName'     => $author?->username() ?? 'System',
                'locked'         => $thread->isLocked(),
                'sticky'         => $thread->isPinned(),
                'postCount'      => $thread->postTotal(),
                'lastID'         => $thread->lastPostId(),
                'lastTime'       => $thread->lastPostTime(),
                'lastAuthorId'   => $thread->lastAuthorId(),
                'lastAuthorName' => $lastAuthor?->username() ?? 'System',
                'lastReadPage'   => $lastRead[$thread->id()]['page'] ?? 0,
                'lastReadPostId' => $lastRead[$thread->id()]['post_id'] ?? 0,
                'read'           => !$unread,
            ];
        }

        return [
            'forumName'   => $this->forum->name(),
            'currentPage' => $this->page,
            'pages'       => (int)ceil($this->forum->numThreads() / TOPICS_PER_PAGE),
            'threads'     => $list,
        ];
    }
}
