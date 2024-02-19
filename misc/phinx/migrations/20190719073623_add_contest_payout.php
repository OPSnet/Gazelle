<?php

use Phinx\Migration\AbstractMigration;

class AddContestPayout extends AbstractMigration {
    public function change(): void {
        $this->table('contest_has_bonus_pool')
            ->addColumn('Status', 'enum', ['values' => ['open', 'ready', 'paid'], 'default' => 'open'])
            ->update();
    }
}
