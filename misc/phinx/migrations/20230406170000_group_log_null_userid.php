<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class GroupLogNullUserID extends AbstractMigration
{
    public function up(): void
    {
        $this->table('group_log')
            ->changeColumn('UserID', 'integer', ['null' => true, 'default' => null])
            ->save();
    }

    public function down(): void
    {
        $this->table('group_log')
            ->changeColumn('UserID', 'integer', ['null' => false, 'default' => 0])
            ->save();
    }
}
