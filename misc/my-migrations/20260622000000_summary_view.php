<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SummaryView extends AbstractMigration {
    public function up(): void {
        $this->query("
            CREATE VIEW v_summary_collages AS
            SELECT UserID AS id_user,
                count(*)  AS total
            FROM collages
            WHERE Deleted = '0'
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_collages_artists AS
            SELECT ca.UserID                AS id_user,
                count(DISTINCT ca.ArtistID) AS total
            FROM collages_artists ca
            INNER JOIN collages   c on (c.ID = ca.CollageID and c.Deleted = '0')
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_collages_torrents AS
            SELECT ct.UserID               AS id_user,
                count(DISTINCT ct.GroupID) AS total
            FROM collages_torrents ct
            INNER JOIN collages    c on (c.ID = ct.CollageID and c.Deleted = '0')
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_forums_posts AS
            SELECT AuthorID AS id_user,
                count(*)    AS total
            FROM forums_posts
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_forums_topics AS
            SELECT AuthorID AS id_user,
                count(*)    AS total
            FROM forums_topics
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_seed_bp AS
            SELECT xfu.uid AS id_user,
                sum(category_bonus_accrual(t.Size, xfh.seedtime, tls.Seeders, c.bonus_scale))
                    AS bp_hourly_accrual
            FROM (
                SELECT DISTINCT uid, fid
                FROM users_main um
                INNER JOIN xbt_files_users x on (um.ID = x.uid)
                WHERE um.Enabled    = '1'
                    AND x.active    = 1
                    AND x.remaining = 0
                    AND x.mtime     > unix_timestamp(now() - INTERVAL 1 HOUR)
            ) AS xfu
            INNER JOIN xbt_files_history    xfh using (uid, fid)
            INNER JOIN torrents             t   on (t.ID          = xfu.fid)
            INNER JOIN torrents_leech_stats tls on (tls.TorrentID = t.ID)
            INNER JOIN torrents_group       tg  on (tg.ID         = t.GroupID)
            INNER JOIN category             c   on (c.category_id   = tg.CategoryID)
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_seed_seeding AS
            SELECT xfu.uid AS id_user,
                sum(CASE WHEN xfu.remaining > 0 THEN 1 ELSE 0 END) AS leeching,
                sum(CASE WHEN xfu.remaining = 0 THEN 1 ELSE 0 END) AS seeding
            FROM users_main            um
            INNER JOIN xbt_files_users xfu ON (xfu.uid = um.ID)
            INNER JOIN torrents        t   ON (t.ID = xfu.fid)
            WHERE um.Enabled = '1'
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_seed_seedtime AS
            SELECT uid        AS id_user,
                sum(seedtime) AS total
            FROM xbt_files_history
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_seed_snatch AS
            SELECT uid             AS id_user,
               count(*)            AS snatch_total,
               count(distinct fid) AS snatch_unique
           FROM xbt_snatched
           GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_tgroup AS
            SELECT t.UserID               AS id_user,
                count(*)                  AS total,
                count(DISTINCT t.GroupID) AS unique_group,
                count(DISTINCT CASE WHEN
                    t.Format = 'FLAC'
                    AND (
                        (t.Media = 'CD' and t.LogScore = 100)
                        OR (t.Media in ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                    ) THEN t.GroupID
                    END
                ) AS perfect_flac,
                count(DISTINCT CASE WHEN
                    t.Format = 'FLAC'
                    AND (
                        (t.Media = 'CD' and t.LogScore = 100)
                            or (t.Media in ('Vinyl', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                        )
                        THEN t.GroupID
                    END
                ) AS perfecter_flac
            FROM torrents t
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_torrents_artists AS
            SELECT UserID AS id_user,
                count(*)  AS total
            FROM torrents_artists
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_users_downloads AS
            SELECT UserID AS id_user,
                count(*)       AS dl_total,
                count(distinct TorrentID)
                               AS dl_unique
            FROM users_downloads
            GROUP BY id_user
        ");
        $this->query("
            CREATE VIEW v_summary_user_inviter AS
            SELECT inviter_user_id AS id_user,
                count(*)           AS total
            FROM users_main
            WHERE inviter_user_id > 0
            GROUP BY id_user
        ");
    }

    public function down(): void {
        foreach (
            [
                'v_summary_collages',
                'v_summary_collages_artists',
                'v_summary_collages_torrents',
                'v_summary_forums_posts',
                'v_summary_forums_topics',
                'v_summary_seed_bp',
                'v_summary_seed_seeding',
                'v_summary_seed_seedtime',
                'v_summary_seed_snatch',
                'v_summary_tgroup',
                'v_summary_torrents_artists',
                'v_summary_users_downloads',
                'v_summary_user_inviter',
            ] as $t
        ) {
            $this->query("
                drop view $t
            ");
        }
    }
}
