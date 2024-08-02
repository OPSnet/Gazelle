<?php

use Phinx\Migration\AbstractMigration;

class UserAttrSeedbox extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')
            ->insert([
                [
                    'Name' => 'feature-seedbox',
                    'Description' => 'This user has purchased the seedbox feature'
                ],
            ])
            ->save();
        $this->table('bonus_item')
            ->insert([
                [
                    'Price' => 8000,
                    'Amount' => 1,
                    'MinClass' => 150,
                    'FreeClass' => 999999,
                    'Label' => 'seedbox',
                    'Title' => 'Unlock the Seedbox viewer',
                    'sequence' => 14,
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->execute("
            DELETE FROM user_attr WHERE Name = 'feature-seedbox'
        ");
        $this->execute("
            DELETE FROM bonus_item WHERE Label = 'seedbox'
        ");
    }
}
