<?php

use Phinx\Seed\AbstractSeed;
use Phinx\Util\Literal;

require_once(__DIR__ . '/../../lib/util.php'); // for randomString()

class AaaInitialUserSeeder extends AbstractSeed {
    public function run() {
        /** @var \PDOStatement $stmt */
        $stmt = $this->getAdapter()->getConnection()->prepare("
            SELECT 1
            FROM users_main
            WHERE PermissionID = ?
            LIMIT 1
        ");
        $stmt->execute([SYSOP]);
        if ($stmt->fetch(\PDO::FETCH_NUM) !== false) {
            // There is a Sysop-level user, consider the database seeded
            return;
        }

        $this->table('users_main')->insert([
            [
                'Username' => 'admin',
                'Email' => 'admin@example.com',
                'PassHash' => password_hash(hash('sha256','password'), PASSWORD_DEFAULT),
                'torrent_pass' => randomString(32),
                'PermissionID' => SYSOP,
                'Invites' => STARTING_INVITES,
                'Enabled' => '1',
                'Visible' => '1',
                'can_leech' => 1,
            ],
            [
                'Username' => 'user',
                'Email' => 'user@example.com',
                'PassHash' => password_hash(hash('sha256','password'), PASSWORD_DEFAULT),
                'torrent_pass' => randomString(32),
                'PermissionID' => USER,
                'Invites' => STARTING_INVITES,
                'Enabled' => '1',
                'Visible' => '1',
                'can_leech' => 1,
            ],
        ])->saveData();

        $stmt = $this->query("SELECT ID FROM users_main WHERE Username='admin'");
        /** @var \PDOStatement $stmt */
        $adminId = (int) $stmt->fetch()['ID'];
        $userId = $adminId + 1;

        $this->table('user_last_access')->insert([
            [
                'user_id' => $adminId,
            ],
            [
                'user_id' => $userId,
            ],
        ])->saveData();

        $this->table('users_leech_stats')->insert([
            [
                'UserID' => $adminId,
                'Uploaded' => STARTING_UPLOAD
            ],
            [
                'UserID' => $userId,
                'Uploaded' => STARTING_UPLOAD
            ]
        ])->saveData();

        $this->table('users_info')->insert([
            [
                'UserID' => $adminId,
                'StyleID' => 18,
                'TorrentGrouping' => 0,
                'ShowTags' => 1,
                'AuthKey' => '7d3b4750ea71502d25051875a250b71a',
                'Inviter' => 0,
                'Info' => 'Created by installation script',
                'AdminComment' => '',
                'SiteOptions' => serialize([]),
            ],
            [
                'UserID' => $userId,
                'StyleID' => 1,
                'TorrentGrouping' => 0,
                'ShowTags' => 1,
                'AuthKey' => 'a1189fa8554776c6de31b6b4e2d0faea',
                'Inviter' => 0,
                'Info' => 'Created by installation script',
                'AdminComment' => '',
                'SiteOptions' => serialize([]),
            ]
        ])->saveData();

        $this->table('users_history_emails')->insert([
            [
                'UserID' => $adminId,
                'Email' => 'admin@example.com',
                'IP' => '127.0.0.1',
                'useragent' => 'initial-seed',
            ],
            [
                'UserID' => $userId,
                'Email' => 'user@example.com',
                'IP' => '127.0.0.1',
                'useragent' => 'initial-seed',
            ]
        ])->saveData();

        $this->table('users_notifications_settings')->insert([
            ['UserID' => $adminId],
            ['UserID' => $userId]
        ])->saveData();

        $tables = [
            'user_flt',
            'user_bonus',
        ];

        foreach ($tables as $table) {
            $this->table($table)->insert([
                ['user_id' => $adminId],
                ['user_id' => $userId],
            ])->saveData();
        }
    }
}
