<?php

namespace Gazelle\WitnessTable;

class UserReadForum extends AbstractWitnessTable {
    protected function reference(): string   { return ''; }
    protected function tableName(): string   { return 'user_read_forum'; }
    protected function idColumn(): string    { return 'user_id'; }
    protected function valueColumn(): string { return 'last_read'; }

    public function witness(int $userId): bool {
        return $this->witnessDate($userId);
    }
}
