<?php

namespace Gazelle\WitnessTable;

class UserReadNews extends AbstractWitnessTable {
    protected function reference(): string   { return 'news'; }
    protected function tableName(): string   { return 'user_read_news'; }
    protected function idColumn(): string    { return 'user_id'; }
    protected function valueColumn(): string { return 'news_id'; }

    public function witness(int $userId): bool {
        return $this->witnessValue($userId);
    }
}
