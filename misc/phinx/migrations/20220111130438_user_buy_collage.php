<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserBuyCollage extends AbstractMigration
{
    public function up(): void {
        $this->query("
            UPDATE bonus_item SET MinClass = 0 WHERE label = 'collage-1' and MinClass != 0
        ");
    }

    public function down(): void {
        $this->query("
            UPDATE bonus_item SET MinClass = 150 WHERE label = 'collage-1' and MinClass != 150
        ");
    }
}
