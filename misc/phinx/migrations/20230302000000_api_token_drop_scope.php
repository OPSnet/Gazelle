<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class ApiTokenDropScope extends AbstractMigration {
    public function up(): void {
        $this->table('api_tokens')->removeColumn('scope')->save();
    }

    public function down(): void {
        $this->table('api_tokens')->addColumn('scope', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])->save();
    }
}
