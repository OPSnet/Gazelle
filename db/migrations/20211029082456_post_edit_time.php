<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PostEditTime extends AbstractMigration
{
    public function up(): void {
        $this->execute("UPDATE comments_edits SET EditTime = now() WHERE EditTime IS NULL OR EditTime = '0000-00-00 00:00:00'");

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
