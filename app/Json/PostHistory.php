<?php

namespace Gazelle\Json;

class PostHistory extends \Gazelle\Json {

    public function __construct(
        protected \Gazelle\Search\Forum $search,
        protected \Gazelle\Manager\User $userMan,
        protected \Gazelle\Util\Paginator $paginator,
    ) { }

    public function payload(): array {
        $this->paginator->setTotal($this->search->postHistoryTotal());
        $posts = $this->search->postHistoryPage($this->paginator->limit(), $this->paginator->offset());
        $thread = [];
        foreach ($posts as $p) {
            $thread[] = [
                'postId'         => $p['post_id'],
                'topicId'        => $p['thread_id'],
                'threadTitle'    => $p['title'],
                'lastPostId'     => $p['last_post_id'],
                'lastRead'       => $p['last_read'] ?? null,
                'locked'         => (bool)$p['is_locked'],
                'sticky'         => (bool)$p['is_sticky'],
                'addedTime'      => $p['added_time'],
                'body'           => \Text::full_format($p['body']),
                'bbbody'         => $p['body'],
                'editedUserId'   => $p['edited_user_id'] == 0 ? null : $p['edited_user_id'],
                'editedUsername' => $this->userMan->findById($p['edited_user_id'] ?? 0)?->username(),
                'editedTime'     => $p['edited_time'],
            ];
        }
        return [
            'currentPage' => $this->paginator->page(),
            'pages'       => $this->paginator->pages(),
            'messages'    => $thread,
        ];
    }
}
