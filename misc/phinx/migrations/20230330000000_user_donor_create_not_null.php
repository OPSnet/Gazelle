<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class UserDonorCreateNotNull extends AbstractMigration {
    public function up(): void {
        $this->table('users_donor_ranks')
            ->changeColumn('SpecialRank',         'integer', ['default' => 0, 'limit' => MysqlAdapter::INT_TINY])
            ->changeColumn('InvitesReceivedRank', 'integer', ['default' => 0, 'limit' => MysqlAdapter::INT_TINY])
            ->save();
    }

    public function down(): void {
        $this->table('users_donor_ranks')
            ->changeColumn('SpecialRank',         'integer', ['default' => 0, 'limit' => MysqlAdapter::INT_TINY, 'null' => true])
            ->changeColumn('InvitesReceivedRank', 'integer', ['default' => 0, 'limit' => MysqlAdapter::INT_TINY, 'null' => true])
            ->save();
    }
}
