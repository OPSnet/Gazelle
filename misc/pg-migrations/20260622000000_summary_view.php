<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SummaryView extends AbstractMigration {
    public function up(): void {
        $MYSQL_DB = MYSQL_DB;

        /* on a fresh setup, these tables will already exist */
        $this->query("
            create foreign table if not exists relay.v_summary_collages (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_collages')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_collages_artists (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_collages_artists')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_collages_torrents (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_collages_torrents')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_forums_posts (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_forums_posts')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_forums_topics (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_forums_topics')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_seed_bp (
                id_user           int   not null,
                bp_hourly_accrual float not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_seed_bp')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_seed_seeding (
                id_user  int not null,
                leeching int not null,
                seeding  int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_seed_seeding')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_seed_seedtime (
                id_user int    not null,
                total   bigint not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_seed_seedtime')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_seed_snatch (
                id_user       int not null,
                snatch_total  int not null,
                snatch_unique int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_seed_snatch')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_tgroup (
                id_user        int    not null,
                total          bigint not null,
                unique_group   int    not null,
                perfect_flac   int    not null,
                perfecter_flac int    not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_tgroup')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_torrents_artists (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_torrents_artists')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_users_downloads (
                id_user   int not null,
                dl_total  int not null,
                dl_unique int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_users_downloads')
        ");
        $this->query("
            create foreign table if not exists relay.v_summary_user_inviter (
                id_user int not null,
                total   int not null
            )
            server relayer options (dbname '$MYSQL_DB', table_name 'v_summary_user_inviter')
        ");
    }

    public function down(): void {
        foreach (
            [
                'relay.v_summary_collages',
                'relay.v_summary_collages_artists',
                'relay.v_summary_collages_torrents',
                'relay.v_summary_forums_posts',
                'relay.v_summary_forums_topics',
                'relay.v_summary_seed_bp',
                'relay.v_summary_seed_seeding',
                'relay.v_summary_seed_seedtime',
                'relay.v_summary_seed_snatch',
                'relay.v_summary_tgroup',
                'relay.v_summary_torrents_artists',
                'relay.v_summary_users_downloads',
                'relay.v_summary_user_inviter',
            ] as $t
        ) {
            $this->query("
                drop foreign table $t
            ");
        }
    }
}
