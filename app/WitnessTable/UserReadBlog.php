<?php

namespace Gazelle\WitnessTable;

class UserReadBlog extends AbstractWitnessTable {
    protected function reference()   { return 'blog'; }
    protected function tableName()   { return 'user_read_blog'; }
    protected function idColumn()    { return 'user_id'; }
    protected function valueColumn() { return 'blog_id'; }

    public function witness(int $userId): bool {
        $result = $this->witnessValue($userId);
        if ($result) {
            $this->cache->deleteMulti(["u_$userId", "user_info_heavy_$userId"]);
        }
        return $result;
    }
}
