<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TgroupArtwork extends AbstractMigration {
    public function change(): void {
        $this->table('tgroup_artwork', ['id' => false, 'primary_key' => 'id_tgroup_artwork'])
            ->addColumn('id_tgroup_artwork', 'integer', ['identity' => true])
            ->addColumn('id_tgroup', 'integer')
            ->addColumn('id_user', 'integer')
            ->addColumn('created', 'timestamp',
                ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('url', 'string', ['length' => 255])
            ->addColumn('description', 'text')
            ->addIndex(['id_tgroup'], ['name' => 'tgaw_tg_idx'])
            ->save();
    }
}
