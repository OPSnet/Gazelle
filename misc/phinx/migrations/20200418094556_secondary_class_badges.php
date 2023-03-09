<?php

use Phinx\Migration\AbstractMigration;

class SecondaryClassBadges extends AbstractMigration {
    public function up(): void {
        $this->table('permissions')
             ->addColumn('badge', 'string', ['default' => '', 'limit' => 5])
             ->update();

        $classes = [
            23 => 'FLS', // First Line Support
            30 => 'IN', // Interviewer
            31 => 'TC', // Torrent Celebrity
            32 => 'D', // Designer
            37 => 'AR', // Archive Team
            36 => 'AT', // Alpha Team
            48 => 'BT', // Beta TEam
            38 => 'CT', // Charlie Team
        ];

        foreach ($classes as $id => $badge) {
            $this->getQueryBuilder()
                 ->update('permissions')
                 ->set('badge', $badge)
                 ->where(['ID' => $id])
                 ->execute();
        }
    }

    public function down(): void {
        $this->table('permissions')
             ->removeColumn('badge')
             ->update();
    }
}
