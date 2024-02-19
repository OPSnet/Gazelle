<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserInfoAttr extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')->insert(
            [
                ['Name' => 'download-as-text',      'Description' => 'Download files as text/plain MIME type'],
                ['Name' => 'hide-tags',             'Description' => 'Do not display tags where applicable'],
                ['Name' => 'no-pm-delete-download', 'Description' => 'Do not receive system PMs when a downloaded torrent is removed'],
                ['Name' => 'no-pm-delete-seed',     'Description' => 'Do not receive system PMs when a seeding torrent is removed'],
                ['Name' => 'no-pm-delete-snatch',   'Description' => 'Do not receive system PMs when a snatched torrent is removed'],
            ]
        )->save();

        $this->query("
            INSERT INTO user_has_attr (UserID, UserAttrID)
                  SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'download-as-text')      FROM users_info WHERE DownloadAlt = '1'
            UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'hide-tags')             FROM users_info WHERE ShowTags = '0'
            UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'no-pm-delete-download') FROM users_info WHERE NotifyOnDeleteDownloaded = '0'
            UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'no-pm-delete-seed')     FROM users_info WHERE NotifyOnDeleteSeeding = '0'
            UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'no-pm-delete-snatch')   FROM users_info WHERE NotifyOnDeleteSnatched = '0'
        ");
    }

    public function down(): void {
        $this->execute("
            DELETE uha
            FROM user_has_attr uha
            INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrId)
            WHERE ua.Name IN (
                'download-as-text',
                'hide-tags',
                'no-pm-delete-download',
                'no-pm-delete-seed',
                'no-pm-delete-snatch'
            )
        ");
        $this->execute("
            DELETE FROM user_attr
            WHERE Name IN (
                'download-as-text',
                'hide-tags',
                'no-pm-delete-download',
                'no-pm-delete-seed',
                'no-pm-delete-snatch'
            )
        ");
    }
}
