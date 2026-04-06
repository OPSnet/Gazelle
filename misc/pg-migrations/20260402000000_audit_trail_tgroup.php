<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditTrailTgroup extends AbstractMigration {
    public function change(): void {
        $this->table('audit_trail_tgroup', ['id' => false, 'primary_key' => ['id_audit_trail_tgroup']])
            ->addColumn('id_audit_trail_tgroup', 'integer', ['identity' => true])
            ->addColumn('id_tgroup', 'integer')
            ->addColumn('id_torrent', 'integer', ['null' => true])
            ->addColumn('id_user', 'integer', ['null' => true])
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('note', 'text')
            ->addColumn('is_visible', 'boolean', ['default' => true])
            ->addIndex('id_tgroup', ['name' => 'tat_tg_idx'])
            ->addIndex('id_torrent', ['name' => 'tat_t_idx'])
            ->save();
    }
}
