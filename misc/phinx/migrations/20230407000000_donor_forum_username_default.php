<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class DonorForumUsernameDefault extends AbstractMigration {
    public function up(): void {
        $this->table('donor_forum_usernames')
            ->changeColumn('Prefix', 'string', ['null' => false, 'default' => ''])
            ->changeColumn('Suffix', 'string', ['null' => false, 'default' => ''])
            ->changeColumn('UseComma', 'boolean', ['null' => false, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY])
            ->save();
    }

    public function down(): void {
        $this->table('donor_forum_usernames')
            ->changeColumn('Prefix', 'string', ['null' => false])
            ->changeColumn('Suffix', 'string', ['null' => false])
            ->changeColumn('UseComma', 'boolean', ['null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY])
            ->save();
    }
}
