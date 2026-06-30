<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUserSummary extends AbstractMigration {
    public function up(): void {
        $this->table('user_summary')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE user_summary (
                user_id int NOT NULL,
                artist_added_total int NOT NULL DEFAULT '0',
                collage_total int NOT NULL DEFAULT '0',
                collage_contrib int NOT NULL DEFAULT '0',
                download_total int NOT NULL DEFAULT '0',
                download_unique int NOT NULL DEFAULT '0',
                fl_token_total int NOT NULL DEFAULT '0',
                forum_post_total int NOT NULL DEFAULT '0',
                forum_thread_total int NOT NULL DEFAULT '0',
                invited_total int NOT NULL DEFAULT '0',
                leech_total int NOT NULL DEFAULT '0',
                perfect_flac_total int NOT NULL DEFAULT '0',
                perfecter_flac_total int NOT NULL DEFAULT '0',
                request_bounty_total int NOT NULL DEFAULT '0',
                request_bounty_size bigint NOT NULL DEFAULT '0',
                request_created_total int NOT NULL DEFAULT '0',
                request_created_size bigint NOT NULL DEFAULT '0',
                request_vote_total int NOT NULL DEFAULT '0',
                request_vote_size bigint NOT NULL DEFAULT '0',
                seeding_total int NOT NULL DEFAULT '0',
                snatch_total int NOT NULL DEFAULT '0',
                snatch_unique int NOT NULL DEFAULT '0',
                unique_group_total int NOT NULL DEFAULT '0',
                upload_total int NOT NULL DEFAULT '0',
                seedtime_hour bigint NOT NULL DEFAULT '0',
                bp_hourly_accrual float NOT NULL DEFAULT '0',
                PRIMARY KEY (user_id),
                CONSTRAINT user_summary_ibfk_1 FOREIGN KEY (user_id) REFERENCES users_main (ID) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ");
    }
}
