<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CommentUpdateEditedTime extends AbstractMigration {
    public function up(): void {
        $this->table('comments')
            ->changeColumn('EditedTime', 'datetime', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
            ->save();
    }

    public function down(): void {
        $this->table('comments')
            ->changeColumn('EditedTime', 'datetime', ['null' => true])
            ->save();
    }
}
