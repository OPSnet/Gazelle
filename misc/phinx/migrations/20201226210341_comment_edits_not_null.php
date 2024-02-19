<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class CommentEditsNotNull extends AbstractMigration {
    public function up(): void {
        $this->table('comments_edits')
            ->changeColumn('Page', 'enum', [
                'null' => false,
                'values' => ['forums','artist','collages','requests','torrents'],
            ])
            ->changeColumn('Body', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
            ])
            ->changeColumn('EditTime', 'datetime', ['default' => null, 'null' => true, 'update' => 'current_timestamp'])
            ->changeColumn('PostID', 'integer', ['length' => 10, 'null' => false])
            ->changeColumn('EditUser', 'integer', ['length' => 10, 'null' => false])
            ->save();

        $this->execute('ALTER TABLE comments_edits ADD COLUMN comments_edits_id int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY');

        $this->table('comments')
            ->changeColumn('Body', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
            ])
            ->save();
    }

    public function down(): void {
        $this->table('comments_edits')
            ->changeColumn('Page', 'enum', [
                'null' => true,
                'values' => ['forums','artist','collages','requests','torrents'],
            ])
            ->changeColumn('Body', 'text', [
                'null' => true,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('PostID', 'integer', ['length' => 10, 'null' => true])
            ->changeColumn('EditUser', 'integer', ['length' => 10, 'null' => true])
            ->changeColumn('EditTime', 'datetime', ['null' => true])
            ->removeColumn('comments_edits_id')
            ->save();

        $this->execute('ALTER TABLE comments_edits DROP COLUMN comments_edits_id');

        $this->table('comments')
            ->changeColumn('Body', 'text', [
                'null' => true,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->save();
    }
}
