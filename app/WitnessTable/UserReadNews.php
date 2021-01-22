<?php

namespace Gazelle\WitnessTable;

class UserReadNews extends AbstractWitnessTable {
    protected function reference()   { return 'news'; }
    protected function tableName()   { return 'user_read_news'; }
    protected function idColumn()    { return 'user_id'; }
    protected function valueColumn() { return 'news_id'; }

    public function witness(int $userId): bool {
        return $this->witnessValue($userId);
    }
}
