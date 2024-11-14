<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class WikiUtf8mb4 extends AbstractMigration
{
    public function up(): void {
        $this->table('wiki_articles')
            ->changeColumn('Title', 'string', ['null' => false, 'limit' => 100, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->changeColumn('Body', 'text', ['null' => false, 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4', 'limit' => MysqlAdapter::TEXT_MEDIUM])
            ->changeColumn('MinClassRead', 'integer', [ 'null' => false])
            ->changeColumn('MinClassEdit', 'integer', [ 'null' => false])
            ->changeColumn('Date', 'datetime', [ 'null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->changeColumn('Author', 'integer', ['null' => false, 'limit' => 11])
            ->save();
    }

    public function down(): void {
        $this->table('wiki_articles')
            ->changeColumn('Title', 'string', ['null' => true, 'limit' => 100, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->changeColumn('Body', 'text', ['null' => true, 'default' => null, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8', 'limit' => MysqlAdapter::TEXT_MEDIUM])
            ->changeColumn('MinClassRead', 'integer', [ 'null' => true])
            ->changeColumn('MinClassEdit', 'integer', [ 'null' => true])
            ->changeColumn('Date', 'datetime', [ 'null' => true])
            ->changeColumn('Author', 'integer', ['null' => true, 'limit' => 11])
            ->save();
    }
}
