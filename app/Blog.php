<?php

namespace Gazelle;

class BlogNotFoundException extends \Exception {}

class Blog extends Base {
    protected $id;
    protected $title;
    protected $body;
    protected $topicId;

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
        [$this->title, $this->body, $this->topicId] = $this->db->row("
            SELECT Title, Body, ThreadID
            FROM blog
            WHERE ID = ?
            ", $this->id
        );
        if (!$this->title) {
            throw new BlogNotFoundException($id);
        }
    }

    /**
     * The ID of the blog
     * @return int $id
     */
    public function id(): int {
        return $this->id;
    }

    /**
     * The title of the blog
     * @return string $title
     */
    public function title(): string {
        return $this->title;
    }

    /**
     * The body of the blog
     * @return string $body
     */
    public function body(): string {
        return $this->body;
    }

    /**
     * The forum topic ID of the blog
     * @return int $topicId
     */
    public function topicId(): int {
        return $this->topicId;
    }
}
