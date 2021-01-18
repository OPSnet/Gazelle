<?php

namespace Gazelle\WitnessTable;

class UserReadBlog extends AbstractWitnessTable {
    protected function tableName()   { return 'user_read_blog'; }
    protected function idColumn()    { return 'user_id'; }
    protected function valueColumn() { return 'blog_id'; }

    public function witness(int $id) {
        return $this->witnessValue($id, $this->db->scalar("SELECT max(ID) FROM Blog"));
    }
}
