<?php

namespace Gazelle\WitnessTable;

class UserReadNews extends AbstractWitnessTable {
    protected function tableName()   { return 'user_read_news'; }
    protected function idColumn()    { return 'user_id'; }
    protected function valueColumn() { return 'news_id'; }

    public function witness(int $id) {
        return $this->witnessValue($id, $this->db->scalar("SELECT max(ID) FROM news"));
    }
}
