<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserNavigationJson extends AbstractMigration {
    public function change(): void {
        $this->table('users_main')
            ->addColumn('nav_list', 'json', ['null' => true])
            ->save();
    }
}
