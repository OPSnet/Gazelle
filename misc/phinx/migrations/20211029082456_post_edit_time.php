<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PostEditTime extends AbstractMigration
{
    public function up(): void {
        $this->table('comments_edits')
            ->changeColumn('EditTime', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->save();
    }

    public function down(): void {
        $this->table('comments_edits')
            ->changeColumn('EditTime', 'datetime', ['null' => true, 'update' => 'current_timestamp'])
            ->save();
    }
}
