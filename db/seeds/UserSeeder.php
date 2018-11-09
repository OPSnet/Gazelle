<?php

use Phinx\Seed\AbstractSeed;

class UserSeeder extends AbstractSeed {
    public function run() {
        $stmt = $this->query("SELECT COUNT(*) FROM users_main WHERE Username='admin'");
        if (count($stmt->fetchAll()) > 0) {
            return;
        }

        $this->table('users_main')->insert([
			[
				'Username' => 'admin',
				'Email' => 'admin@example.com',
				'PassHash' => password_hash('password', PASSWORD_DEFAULT),
				'Class' => 5,
				'Uploaded' => 3221225472,
				'Enabled' => '1',
				'Visible' => 1,
				'Invites' => 0,
				'PermissionID' => 15,
				'can_leech' => 1,
				'torrent_pass' => '86519d75682397913039534ea21a4e45',
			],
			[
				'Username' => 'user',
				'Email' => 'user@example.com',
				'PassHash' => password_hash('password', PASSWORD_DEFAULT),
				'Class' => 5,
				'Uploaded' => 3221225472,
				'Enabled' => '1',
				'Visible' => 1,
				'Invites' => 0,
				'PermissionID' => 2,
				'can_leech' => 1,
				'torrent_pass' => '86519d75682397913039534ea21a4e45',
			],
		])->saveData();


		$this->table('users_info')->insert([
			[
				'UserID' => 1,
				'StyleID' => 18,
				'TorrentGrouping' => 0,
				'ShowTags' => 1,
				'AuthKey' => '7d3b4750ea71502d25051875a250b71a',
				'JoinDate' => '2018-03-08 15:50:31',
				'Inviter' => 0,
			],
			[
				'UserID' => 2,
				'StyleID' => 1,
				'TorrentGrouping' => 0,
				'ShowTags' => 1,
				'AuthKey' => 'a1189fa8554776c6de31b6b4e2d0faea',
				'JoinDate' => '2018-03-09 05:04:07',
				'Inviter' => 0,
			]
		])->saveData();

		$this->table('users_history_emails')->insert([
			[
				'UserID' => 1,
				'Email' => 'admin@example.com',
				'Time' => null,
				'IP' => '127.0.0.1'
			],
			[
				'UserID' => 2,
				'Email' => 'user@example.com',
				'Time' => null,
				'IP' => '127.0.0.1'
			]
		])->saveData();

		$this->table('users_notifications_settings')->insert([
            ['UserID' => 1],
            ['UserID' => 2]
        ])->saveDate();
    }
}