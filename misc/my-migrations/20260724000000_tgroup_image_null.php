<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TgroupImageNull extends AbstractMigration {
    public function up(): void {
        $this->table('torrents_group')
            ->changeColumn('WikiImage', 'string', ['length' => 255, 'null' => true])
            ->save();
        $this->query("
            UPDATE torrents_group SET
                WikiImage = NULL
            WHERE WikiImage = ''
        ");
    }

    public function down(): void {
        $this->query("
            UPDATE torrents_group SET
                WikiImage = ''
            WHERE WikiImage IS NULL
        ");
        $this->table('torrents_group')
            ->changeColumn('WikiImage', 'string', ['length' => 255, 'null' => false])
            ->save();
    }
}
