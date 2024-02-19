<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MoreUserStats extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_summary', ['id' => false, 'primary_key' => 'user_id'])
             ->addColumn('user_id', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('artist_added_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('collage_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('collage_contrib', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('download_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('download_unique', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('fl_token_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('forum_post_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('forum_thread_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('invited_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('leech_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('perfect_flac_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('perfecter_flac_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('request_bounty_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('request_bounty_size', 'biginteger', ['limit' => 10, 'default' => 0])
             ->addColumn('request_created_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('request_created_size', 'biginteger', ['limit' => 10, 'default' => 0])
             ->addColumn('request_vote_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('request_vote_size', 'biginteger', ['limit' => 10, 'default' => 0])
             ->addColumn('seeding_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('snatch_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('snatch_unique', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('unique_group_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('upload_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();
    }
}
