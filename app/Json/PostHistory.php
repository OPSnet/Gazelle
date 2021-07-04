<?php

namespace Gazelle\Json;

class PostHistory extends \Gazelle\Json {

    public function __construct() {
        parent::__construct();
    }

    /** @var \Gazelle\Util\Paginator */
    protected $paginator;

    /** @var \Gazelle\Search\Forum */
    protected $search;

    /**
     * Supply a forum search context
     *
     * @param \Gazelle\Search\Forum
     */
    public function setForumSearch(\Gazelle\Search\Forum $search) {
        $this->search = $search;
        return $this;
    }

    /**
     * Supply a paginator
     *
     * @param \Gazelle\Util\Paginator
     */
    public function setPaginator(\Gazelle\Util\Paginator $paginator) {
        $this->paginator = $paginator;
        return $this;
    }

    public function payload(): array {
        $posts = $this->search->postHistoryPage($this->paginator->limit(), $this->paginator->offset());
        $thread = [];
        foreach ($posts as $p) {
            $thread[] = [
                'postId' => $p['post_id'],
            ];
        }
        return [
            'currentPage' => $this->paginator->page(),
            'pages'       => (int)ceil($this->search->postHistoryTotal() / $this->paginator->limit()),
            'messages'    => $thread,
        ];
    }
}
