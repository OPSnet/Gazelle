<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class SaneWikiRevisions extends AbstractMigration
{
    public function up(): void {
        $this->table('wiki_revisions')
            ->changeColumn('Revision', 'integer', ['null' => false, 'limit' => '10', 'default' => 1])
            ->changeColumn('Title', 'string', ['null' => false, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->changeColumn('Body', 'text', ['null' => false, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->changeColumn('Date', 'datetime', [ 'null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->changeColumn('Author', 'integer', ['null' => false, 'signed' => false, 'limit' => '10'])
            ->removeIndex(['ID', 'Revision'])
            ->save();
        $this->execute("ALTER TABLE wiki_revisions ADD PRIMARY KEY (ID, Revision)");
    }

    public function down(): void {
        $this->execute("ALTER TABLE wiki_revisions DROP PRIMARY KEY");
        $this->table('wiki_revisions')
            ->changeColumn('Revision', 'integer', ['null' => false, 'limit' => '10'])
            ->changeColumn('Title', 'string', ['null' => true, 'default' => null, 'limit' => 100, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->changeColumn('Body', 'text', ['null' => true, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->changeColumn('Date', 'datetime', ['null' => true, 'default' => null])
            ->changeColumn('Author', 'integer', ['signed' => true, 'null' => true, 'default' => null, 'limit' => '10'])
            ->addIndex(['ID', 'Revision'], ['name' => 'ID_Revision', 'unique' => false])
            ->save();
    }
}
