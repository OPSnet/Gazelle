<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class UserSummary extends AbstractMigration {
    public function change(): void {
        $this->table('user_summary_fltoken', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user',  'integer', ['identity' => true])
            ->addColumn('gifted',   'integer', ['default' => 0])
            ->addColumn('received', 'integer', ['default' => 0])
            ->addColumn('used',     'integer', ['default' => 0])
            ->save();

        $this->table('user_summary_misc', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user',         'integer', ['identity' => true])
            ->addColumn('collage_created', 'integer', ['default' => 0])
            ->addColumn('collage_contrib', 'integer', ['default' => 0])
            ->addColumn('forum_post',      'integer', ['default' => 0])
            ->addColumn('forum_thread',    'integer', ['default' => 0])
            ->addColumn('invited',         'integer', ['default' => 0])
            ->save();

        $this->table('user_summary_request', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user',       'integer', ['identity' => true])
            ->addColumn('bounty_total',  'integer', ['default' => 0])
            ->addColumn('created_total', 'integer', ['default' => 0])
            ->addColumn('vote_total',    'integer', ['default' => 0])
            ->addColumn('bounty_size',   'biginteger', ['default' => 0])
            ->addColumn('created_size',  'biginteger', ['default' => 0])
            ->addColumn('vote_size',     'biginteger', ['default' => 0])
            ->save();

        $this->table('user_summary_seed', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user',         'integer', ['identity' => true])
            ->addColumn('leeching',        'integer', ['default' => 0])
            ->addColumn('seeding',         'integer', ['default' => 0])
            ->addColumn('snatch_total',    'integer', ['default' => 0])
            ->addColumn('snatch_unique',   'integer', ['default' => 0])
            ->addColumn('bp_accrual_hour', Literal::from('real'), ['default' => 0.0])
            ->addColumn('seedtime_hour',   'biginteger', ['default' => 0])
            ->save();

        $this->table('user_summary_tgroup', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user',         'integer', ['identity' => true])
            ->addColumn('artist_added',    'integer', ['default' => 0])
            ->addColumn('download_total',  'integer', ['default' => 0])
            ->addColumn('download_unique', 'integer', ['default' => 0])
            ->addColumn('perfect_flac',    'integer', ['default' => 0])
            ->addColumn('perfecter_flac',  'integer', ['default' => 0])
            ->addColumn('upload',          'integer', ['default' => 0])
            ->addColumn('unique_group',    'integer', ['default' => 0])
            ->save();
    }
}
