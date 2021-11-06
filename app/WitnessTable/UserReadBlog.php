<?php

namespace Gazelle\WitnessTable;

class UserReadBlog extends AbstractWitnessTable {
    protected function reference()   { return 'blog'; }
    protected function tableName()   { return 'user_read_blog'; }
    protected function idColumn()    { return 'user_id'; }
    protected function valueColumn() { return 'blog_id'; }

    public function witness(int $userId): bool {
        return $this->witnessValue($userId);
    }
}
