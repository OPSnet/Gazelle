<?php

use Phinx\Migration\AbstractMigration;

final class AdminErrorReporting extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')
            ->insert([
                [
                    'Name' => 'admin-error-reporting',
                    'Description' => 'This admin user can see errors in rendered pages'
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->getQueryBuilder()
            ->delete('user_attr')
            ->where(['Name' => 'admin-error-reporting'])
            ->execute();
    }
}
