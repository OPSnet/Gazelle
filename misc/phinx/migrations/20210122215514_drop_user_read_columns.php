<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUserReadColumns extends AbstractMigration
{
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('CatchupTime')
            ->removeColumn('LastReadBlog')
            ->removeColumn('LastReadNews')
            ->save();
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('CatchupTime',  'datetime', ['null' => true])
            ->addColumn('LastReadBlog', 'integer', ['limit' => 10, 'default' => 0])
            ->addColumn('LastReadNews', 'integer', ['limit' => 10, 'default' => 0])
            ->save();
    }
}
