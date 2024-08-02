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
        $this->execute("
            DELETE FROM user_attr WHERE Name = 'admin-error-reporting'
        ");
    }
}
