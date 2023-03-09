<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CollageCategoryUserIndex extends AbstractMigration {
    public function up(): void {
        $this->table('collages')
            ->addIndex(['CategoryID', 'UserID'], [ 'name' => 'c_cat_user_idx', ])
            ->removeIndex(['CategoryID'])
            ->update();
    }

    public function down(): void {
        $this->table('collages')
            ->addIndex(['CategoryID'])
            ->removeIndex(['CategoryID', 'UserID'])
            ->update();
    }
}
