<?php

namespace Gazelle\WitnessTable;

class UserReadForum extends AbstractWitnessTable {
    protected function reference()   { return null; }
    protected function tableName()   { return 'user_read_forum'; }
    protected function idColumn()    { return 'user_id'; }
    protected function valueColumn() { return 'last_read'; }

    public function witness(int $userId): bool {
        return $this->witnessDate($userId);
    }
}
