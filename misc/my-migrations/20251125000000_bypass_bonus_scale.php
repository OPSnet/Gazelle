<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BypassBonusScale extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')
            ->insert([
                [
                    'Name'        => 'bypass-bonus-scale',
                    'Description' => 'This user is not subject to bonus point scaling for tokens',
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->query("
            delete from user_attr where Name = 'bypass-bonus-scale'
        ");
    }
}
