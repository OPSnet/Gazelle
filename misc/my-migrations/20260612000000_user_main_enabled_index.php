<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/* This index is not useful right away, but on a site that has
 * seen a lot of activity it can help cut out a full table scan
 * for operations on active users
 */

final class UserMainEnabledIndex extends AbstractMigration {
    public function change(): void {
        $this->table('users_main')
            ->addIndex(['Enabled'], ['name' => 'um_e_idx'])
            ->save();
    }
}
