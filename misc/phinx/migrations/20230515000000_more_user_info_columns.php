<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MoreUserInfoColumns extends AbstractMigration {
    public function up(): void {
        $this->table('users_main')
            ->addColumn('auth_key',        'string',  ['limit' => 32])
            ->addColumn('avatar',          'string',  ['limit' => 255, 'default' => ''])
            ->addColumn('collage_total',   'integer', ['default' => 0])
            ->addColumn('inviter_user_id', 'integer', ['default' => 0])
            ->addColumn('profile_info',    'text',    ['default' => ''])
            ->addColumn('profile_title',   'string',  ['limit' => 255, 'default' => ''])
            ->addColumn('slogan',          'string',  ['limit' => 255, 'null' => true])
            ->addColumn('stylesheet_id',   'integer')
            ->addColumn('stylesheet_url',  'string',  ['limit' => 255, 'null' => true])
            ->save();

        $this->query("
            UPDATE users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            SET um.auth_key        = ui.AuthKey,
                um.avatar          = ui.Avatar,
                um.collage_total   = ui.collages,
                um.inviter_user_id = coalesce(ui.Inviter, 0),
                um.profile_info    = ui.Info,
                um.profile_title   = ui.InfoTitle,
                um.slogan          = ui.SupportFor,
                um.stylesheet_id   = ui.StyleID,
                um.stylesheet_url  = ui.StyleUrl
        ");
    }

    public function down(): void {
        $this->table('users_main')
            ->removeColumn('auth_key')
            ->removeColumn('avatar')
            ->removeColumn('collage_total')
            ->removeColumn('inviter_user_id')
            ->removeColumn('profile_info')
            ->removeColumn('profile_title')
            ->removeColumn('slogan')
            ->removeColumn('stylesheet_id')
            ->removeColumn('stylesheet_url')
            ->save();
    }
}
