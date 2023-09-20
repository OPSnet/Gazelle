<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ApplicantRoleHasUser extends AbstractMigration {
    public function change(): void {
        $this->table('applicant_role_has_user', ['id' => false, 'primary_key' => ['applicant_role_id', 'user_id']])
            ->addColumn('applicant_role_id', 'integer')
            ->addColumn('user_id', 'integer')
            ->addIndex(['user_id'], ['name' => 'arhu_user_idx'])
            ->addForeignKey('applicant_role_id', 'applicant_role', 'ID')
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->create();
    }
}
