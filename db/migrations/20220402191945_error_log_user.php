<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ErrorLogUser extends AbstractMigration {
    public function up(): void
    {
        $this->table('error_log')
            ->addColumn('user_id', 'integer', ['default' => 0])
            ->save();
    }

    public function down(): void
    {
        $this->table('error_log')
            ->removeColumn('user_id')
            ->save();
    }
}
