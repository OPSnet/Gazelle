<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserAttrForceMfa extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')
             ->insert([
                [
                    'name'        => 'enforce-mfa',
                    'description' => 'This user must have MFA enabled',
                ],
             ])
             ->save();
    }

    public function down(): void {
        $this->query("
            delete from user_attr where Name = 'enforce-mfa';
        ");
    }
}
