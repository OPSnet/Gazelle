<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ApplicantRoleCascade extends AbstractMigration {
    public function up(): void {
        $this->table('applicant')
            ->dropForeignKey('RoleID')
            ->addForeignKey('RoleID', 'applicant_role', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('appplicant')
            ->dropForeignKey('RoleID')
            ->addForeignKey('RoleID', 'applicant_role', 'ID')
            ->save();
    }
}
