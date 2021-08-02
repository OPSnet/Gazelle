<?php

namespace Gazelle;

class Tag extends BaseObject {
    protected $info;

    public function __construct(int $id) {
        parent::__construct($id);
        $this->info = $this->db->rowAssoc("
            SELECT t.Name AS name,
                t.TagType AS type,
                t.Uses AS uses,
                t.UserID AS user_id
            FROM tags t
            WHERE t.ID = ?
            ", $this->id
        );
    }

    public function tableName(): string {
        return 'tags';
    }

    public function flush() {
    }

    public function name(): string {
        return $this->info['name'];
    }

    /**
     * Tag type
     * @return string one of 'genre' or 'other' ('genre' designates an official tag).
     */
    public function type(): string {
        return $this->info['type'];
    }

    /**
     * Number of uses of the tag.
     */
    public function uses(): int {
        return $this->info['uses'];
    }

    /**
     * Who created the tag.
     */
    public function userId(): int {
        return $this->info['user_id'];
    }
}
