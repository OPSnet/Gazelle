<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDeleteCascade extends AbstractMigration {
    public function up(): void {
        $cascade = ['delete' => 'CASCADE', 'update' => 'CASCADE'];
        $this->table('bonus_history')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID', $cascade)
            ->save();
        $this->table('staff_blog_visits')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID', $cascade)
            ->save();
        $this->table('user_bonus')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID', $cascade)
            ->save();
        $this->table('user_flt')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID', $cascade)
            ->save();
        $this->table('user_has_attr')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID', $cascade)
            ->save();
        $this->table('user_last_access')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID', $cascade)
            ->save();
        $this->table('users_leech_stats')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID', $cascade)
            ->save();
    }

    public function down(): void {
        $this->table('bonus_history')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();
        $this->table('staff_blog_visits')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();
        $this->table('user_bonus')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
        $this->table('user_flt')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
        $this->table('user_has_attr')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();
        $this->table('user_last_access')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
        $this->table('users_leech_stats')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();
    }
}
