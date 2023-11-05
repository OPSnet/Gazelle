<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InviteeProfile extends AbstractMigration {
    public function up(): void {
        $this->execute('
            create table user_external_profile (
                id_user int not null primary key,
                profile text not null
            )
        ');
    }

    public function down(): void {
        $this->table('user_external_profile')->drop()->save();
    }
}
