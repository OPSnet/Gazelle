<?php

namespace Gazelle\WitnessTable;

class UserReadBlog extends AbstractWitnessTable {
    protected function reference(): string   { return 'blog'; }
    protected function tableName(): string   { return 'user_read_blog'; }
    protected function idColumn(): string    { return 'user_id'; }
    protected function valueColumn(): string { return 'blog_id'; }

    public function witness(int $userId): bool {
        return $this->witnessValue($userId);
    }
}
