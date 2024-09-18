<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDownloadCreatedIndex extends AbstractMigration {
    public function change(): void {
        $this->table('users_downloads')
            ->addIndex('Time')
            ->save();
    }
}
