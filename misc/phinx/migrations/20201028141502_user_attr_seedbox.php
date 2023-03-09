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
        $this->getQueryBuilder()
            ->delete('user_attr')
            ->where(['Name' => 'feature-seedbox'])
            ->execute();
        $this->getQueryBuilder()
            ->delete('bonus_item')
            ->where(['Label' => 'seedbox'])
            ->execute();
    }
}
