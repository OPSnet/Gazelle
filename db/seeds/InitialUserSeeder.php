<?php

use Phinx\Seed\AbstractSeed;
use Phinx\Util\Literal;

require_once(__DIR__ . '/../../classes/util.php'); // randomString()

class InitialUserSeeder extends AbstractSeed {
    public function run() {
        /** @var \PDOStatement $stmt */
        $stmt = $this->getAdapter()->getConnection()->prepare("
            SELECT 1
            FROM users_main
            WHERE PermissionID = ?
            LIMIT 1
        ");
        $stmt->execute([SYSOP]);
        if ($stmt->fetch(PDO::FETCH_NUM)[0]) {
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
                'Visible' => 1,
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
                'Visible' => 1,
                'can_leech' => 1,
            ],
        ])->saveData();

        $this->table('user_last_access')->insert([
            [ 'user_id' => 1, 'last_access' => Literal::from('now()') ],
            [ 'user_id' => 2, 'last_access' => Literal::from('now()') ],
        ])->saveData();

        $this->table('user_flt')->insert([
            [ 'user_id' => 1 ],
            [ 'user_id' => 2 ],
        ])->saveData();

        $this->table('users_leech_stats')->insert([
            [ 'UserID' => 1, 'Uploaded' => STARTING_UPLOAD ],
            [ 'UserID' => 2, 'Uploaded' => STARTING_UPLOAD ]
        ])->saveData();

        $this->table('users_info')->insert([
            [
                'UserID' => 1,
                'StyleID' => 18,
                'TorrentGrouping' => 0,
                'ShowTags' => 1,
                'AuthKey' => '7d3b4750ea71502d25051875a250b71a',
                'Inviter' => 0,
            ],
            [
                'UserID' => 2,
                'StyleID' => 1,
                'TorrentGrouping' => 0,
                'ShowTags' => 1,
                'AuthKey' => 'a1189fa8554776c6de31b6b4e2d0faea',
                'Inviter' => 0,
            ]
        ])->saveData();

        $this->table('users_history_emails')->insert([
            [
                'UserID' => 1,
                'Email' => 'admin@example.com',
                'Time' => Literal::from('now()'),
                'IP' => '127.0.0.1'
            ],
            [
                'UserID' => 2,
                'Email' => 'user@example.com',
                'Time' => Literal::from('now()'),
                'IP' => '127.0.0.1'
            ]
        ])->saveData();

        $this->table('users_notifications_settings')->insert([
            ['UserID' => 1],
            ['UserID' => 2]
        ])->saveData();
    }
}
