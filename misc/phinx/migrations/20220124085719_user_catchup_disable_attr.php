<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserCatchupDisableAttr extends AbstractMigration
{
    public function change(): void {
        $this->query("
            INSERT IGNORE INTO user_has_attr (UserID, UserAttrID)
                SELECT ID, (SELECT ID FROM user_attr WHERE Name = 'disable-leech') FROM users_main where can_leech != 1
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-avatar')       FROM users_info where DisableAvatar = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-invites')      FROM users_info where DisableInvites = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-posting')      FROM users_info where DisablePosting = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-forums')       FROM users_info where DisableForums = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-bonus-points') FROM users_info where DisablePoints = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-irc')          FROM users_info where DisableIRC = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-tagging')      FROM users_info where DisableTagging = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-upload')       FROM users_info where DisableUpload = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-wiki')         FROM users_info where DisableWiki = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-pm')           FROM users_info where DisablePM = '1'
                UNION SELECT UserID, (SELECT ID FROM user_attr WHERE Name = 'disable-pm')           FROM users_info where DisablePM = '1'
        ");
    }
}
