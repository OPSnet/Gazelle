<?php

namespace Gazelle\Json;

class ForumThread extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\ForumThread    $thread,
        protected \Gazelle\User           $user,
        protected \Gazelle\Util\Paginator $paginator,
        protected bool                    $updateLastRead,
        protected \Gazelle\Manager\User   $userMan,
    ) {}

    public function payload(): array {
        $paginator = $this->paginator;
        $thread = $this->thread;
        $paginator->setTotal($thread->postTotal());
        $slice = $thread->slice($paginator->page(), $paginator->perPage());

        if ($this->updateLastRead) {
            $lastPost = end($slice);
            $lastPostId = $lastPost['ID'];
            reset($slice);
            if (
                $thread->postTotal() <= $paginator->perPage() * $paginator->page()
                && $thread->pinnedPostId() > $lastPostId
            ) {
                $lastPostId = $thread->pinnedPostId();
            }
            // Handle last read
            if (!$thread->isLocked() || $thread->isPinned()) {
                $lastRead = self::$db->scalar("
                    SELECT PostID
                    FROM forums_last_read_topics
                    WHERE UserID = ?
                        AND TopicID = ?
                    ", $this->user->id(), $thread->id()
                );
                if ($lastRead < $lastPost) {
                    self::$db->prepared_query("
                        INSERT INTO forums_last_read_topics
                               (UserID, TopicID, PostID)
                        VALUES (?,      ?,       ?)
                        ON DUPLICATE KEY UPDATE PostID = ?
                        ", $this->user->id(), $thread->id(), $lastPostId, $lastPostId
                    );
                }
            }
        }

        $pollInfo = null;
        if ($thread->hasPoll()) {
            $poll = new \Gazelle\ForumPoll($thread->id());

            $response = $poll->response($this->user);
            $answerList = $poll->vote();
            if ($response > 0 || (!is_null($response) && $poll->hasRevealVotes())) {
                $answerList[$response]['asnswer'] = '» ' . $answerList[$response]['asnswer'];
            }

            $pollInfo = [
                'answers'    => $answerList,
                'closed'     => $poll->isClosed(),
                'featured'   => $poll->isFeatured(),
                'question'   => $poll->question(),
                'maxVotes'   => $poll->max(),
                'totalVotes' => $poll->total(),
                'voted'      => $response !== null || $poll->isClosed() || $thread->isLocked(),
                'vote'       => $response ? $response - 1 : null,
            ];
        }

        // Squeeze in the pinned post
        if ($thread->pinnedPostId()) {
            if ($thread->pinnedPostId() != $slice[0]['ID']) {
                array_unshift($slice, $thread->pinnedPostInfo());
            }
            if ($thread->pinnedPostId() != $slice[count($slice) - 1]['ID']) {
                $slice[] = $thread->pinnedPostInfo();
            }
        }

        $userCache = [];
        $postList = [];
        foreach ($slice as $post) {
            [$postId, $authorId, $addedTime, $body, $editedUserId, $editedTime] = array_values($post);
            if (!isset($userCache[$authorId])) {
                $userCache[$authorId] = $this->userMan->findById((int)$authorId);
            }
            $author = $userCache[$authorId];
            if (!isset($userCache[$editedUserId])) {
                $userCache[$editedUserId] = $this->userMan->findById((int)$editedUserId);
            }
            $editor = $userCache[$editedUserId];

            $postList[] = [
                'postId'         => $postId,
                'addedTime'      => $addedTime,
                'bbBody'         => $body,
                'body'           => \Text::full_format($body),
                'editedUserId'   => $editedUserId,
                'editedTime'     => $editedTime,
                'editedUsername' => $editor ? $editor->username() : null,
                'author' => [
                    'authorId'   => $authorId,
                    'authorName' => $author->username(),
                    'paranoia'   => $author->paranoia(),
                    'donor'      => (new \Gazelle\User\Donor($author))->isDonor(),
                    'warned'     => $author->isWarned(),
                    'avatar'     => $author->avatar(),
                    'enabled'    => $author->isEnabled(),
                    'userTitle'  => $author->title(),
                ],
            ];
        }

        $subscribed = (new \Gazelle\User\Subscription($this->user))->isSubscribed($thread->id());
        if ($subscribed) {
            self::$cache->delete_value("subscriptions_user_new_{$this->user->id()}");
        }

        return [
            'forumId'     => $thread->forum()->id(),
            'forumName'   => $thread->forum()->name(),
            'threadId'    => $thread->id(),
            'threadTitle' => $thread->title(),
            'subscribed'  => $subscribed,
            'locked'      => $thread->isLocked(),
            'sticky'      => $thread->isPinned(),
            'currentPage' => $paginator->page(),
            'pages'       => $paginator->pages(),
            'poll'        => $pollInfo,
            'posts'       => $postList,
        ];
    }
}
