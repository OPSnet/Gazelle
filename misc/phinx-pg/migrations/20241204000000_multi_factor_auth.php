<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MultiFactorAuth extends AbstractMigration {
    public function change(): void {
        $this->table('multi_factor_auth', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user', 'integer', ['identity' => true])
            ->addColumn('secret', 'text')
            ->addColumn('ip', 'inet')
            ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
