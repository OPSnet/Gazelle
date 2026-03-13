<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BetterTgroup extends AbstractMigration {
    public function change(): void {
        $this->table('better_tgroup', ['id' => false, 'primary_key' => ['id_tgroup', 'edition']])
            ->addColumn('id_tgroup', 'integer', ['identity' => true])
            ->addColumn('edition',   'string', ['length' => 180])
            ->addColumn('want_320',  'boolean')
            ->addColumn('want_v0',   'boolean')
            ->save();
    }
}
