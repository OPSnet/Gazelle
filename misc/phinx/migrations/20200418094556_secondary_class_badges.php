<?php

use Phinx\Migration\AbstractMigration;

class SecondaryClassBadges extends AbstractMigration {
    public function up(): void {
        $this->table('permissions')
            ->addColumn('badge', 'string', ['default' => '', 'limit' => 5])
            ->save();

        $this->execute("
            UPDATE permissions SET
                badge = CASE
                    WHEN ID = 23 THEN 'FLS' -- First Line Support
                    WHEN ID = 30 THEN 'IN'  -- Interviewer
                    WHEN ID = 31 THEN 'TC'  -- Torrent Celebrity
                    WHEN ID = 32 THEN 'D'   -- Designer
                    WHEN ID = 36 THEN 'AT'  -- Alpha Team
                    WHEN ID = 37 THEN 'AR'  -- Archive Team
                    WHEN ID = 38 THEN 'CT'  -- Charlie Team
                    WHEN ID = 48 THEN 'BT'  -- Beta Team
                END
            WHERE ID IN (23, 30, 31, 32, 36, 37, 38, 48)
        ");
    }

    public function down(): void {
        $this->table('permissions')
            ->removeColumn('badge')
            ->save();
    }
}
