<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BonusOtherIndex extends AbstractMigration {
    public function change(): void {
        $this->table('bonus_history')
            ->addIndex(['OtherUserId', 'UserId'], ['name' => 'bh_ou_u_idx'])
            ->save();
    }
}
