<?php

use Phinx\Migration\AbstractMigration;

/* Greeting developer, nice to meet you. This migration moves column contents
 * from the torrents and users_main tables to torrent_leech_stats and
 * users_leech_stats, respectively. On a busy site with large tables, this has
 * the potential to lock the tables for a long time and cause timeouts on the
 * site. On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 *
 * Otherwise, you will need to make your own arrangements to insert/update the
 * columns in torrents_leech_stats from torrents, and users_leech_stats from
 * users_main.
 *
 * Afterwards you will need to use gh-ost or ptosc to drop the columns from the
 * original tables. You will need to update the phinxlog table by hand:
 *
 * INSERT INTO phinxlog ('version', 'migration_name', 'start_time', 'end_time', 'breakpoint')
 * VALUES (20200314171641, 'MigrateLeechStatTables', now(), now(), 0);
 */

class MigrateLeechStatTables extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            INSERT INTO torrents_leech_stats
            (TorrentID, Seeders, Leechers, Snatched, Balance, last_action)
            SELECT  ID, Seeders, Leechers, Snatched, balance, last_action
            FROM torrents
            ON DUPLICATE KEY UPDATE TorrentID = TorrentID
        ");
        $this->execute("ALTER TABLE torrents
            DROP COLUMN Seeders,
            DROP COLUMN Leechers,
            DROP COLUMN Snatched,
            DROP COLUMN balance,
            DROP COLUMN last_action
        ");
        $this->execute("
            INSERT INTO users_leech_stats
            (  UserID, Uploaded, Downloaded)
            SELECT ID, Uploaded, Downloaded
            FROM users_main
            ON DUPLICATE KEY UPDATE UserID = UserID
        ");
        $this->execute("ALTER TABLE users_main
            DROP COLUMN Uploaded,
            DROP COLUMN Downloaded
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE torrents
            ADD COLUMN Seeders int(6) NOT NULL DEFAULT 0,
            ADD COLUMN Leechers int(6) NOT NULL DEFAULT 0,
            ADD COLUMN Snatched int(10) unsigned NOT NULL DEFAULT 0,
            ADD COLUMN balance bigint(20) NOT NULL DEFAULT 0,
            ADD COLUMN last_action datetime NOT NULL DEFAULT NULL
        ");
        $this->execute("UPDATE torrents
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = torrents.ID) SET
            torrents.Seeders = tls.Seeders,
            torrents.Leechers = tls.Leechers,
            torrents.Snatched = tls.Snatched,
            torrents.balance = tls.Balance,
            torrents.last_action = tls.last_action
        ");
        $this->execute("ALTER TABLE users_main
            ADD COLUMN Uploaded bigint(20) unsigned NOT NULL DEFAULT 0,
            ADD COLUMN Downloaded bigint(20) unsigned NOT NULL DEFAULT 0
        ");
        $this->execute("UPDATE users_main
            INNER JOIN users_leech_stats uls ON (uls.UserID = users_main.ID) SET
            users_main.Uploaded = uls.Uploaded,
            users_main.Downloaded = uls.Downloaded
        ");
    }
}
