<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LongerPasshash extends AbstractMigration {
    public function change(): void {
        $this->table('users_main')
            ->changeColumn('PassHash', 'string', ['limit' => 255])
            ->save();
    }
}
