<?php


use Phinx\Migration\AbstractMigration;

class UserNav extends AbstractMigration
{
    /**
     * Change Method. ...is what I WOULD say, if that was possible.
     */
    public function up() {
        $this->table('nav_items', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' =>  10, 'signed' => false, 'identity' => true])
             ->addColumn('Key', 'string', ['limit' => 20])
             ->addColumn('Title', 'string', ['limit' => 50])
             ->addColumn('Target', 'string', ['limit' => 200])
             ->addColumn('Tests', 'string', ['limit' => 100])
             ->addColumn('TestUser', 'boolean')
             ->addColumn('Mandatory', 'boolean')
             ->save();

        $fixtures = [
            [
                'Key' => 'inbox',
                'Title' => 'Inbox',
                'Target' => 'inbox.php',
                'Tests' => 'inbox',
                'TestUser' => false,
                'Mandatory' => true
            ],
            [
                'Key' => 'staffinbox',
                'Title' => 'Staff Inbox',
                'Target' => 'staffpm.php',
                'Tests' => 'staffpm',
                'TestUser' => false,
                'Mandatory' => true
            ],
            [
                'Key' => 'uploaded',
                'Title' => 'Uploads',
                'Target' => 'torrents.php?type=uploaded',
                'Tests' => 'torrents,false,uploaded',
                'TestUser' => true,
                'Mandatory' => false
            ],
            [
                'Key' => 'bookmarks',
                'Title' => 'Bookmarks',
                'Target' => 'bookmarks.php?type=torrents',
                'Tests' => 'bookmarks',
                'TestUser' => false,
                'Mandatory' => false
            ],
            [
                'Key' => 'notifications',
                'Title' => 'Notifications',
                'Target' => 'user.php?action=notify',
                'Tests' => 'torrents:notify,user:notify',
                'TestUser' => true,
                'Mandatory' => false
            ],
            [
                'Key' => 'subscriptions',
                'Title' => 'Subscriptions',
                'Target' => 'userhistory.php?action=subscriptions',
                'Tests' => '',
                'TestUser' => false,
                'Mandatory' => false
            ],
            [
                'Key' => 'comments',
                'Title' => 'Comments',
                'Target' => 'comments.php',
                'Tests' => 'comments',
                'TestUser' => true,
                'Mandatory' => false
            ],
            [
                'Key' => 'friends',
                'Title' => 'Friends',
                'Target' => 'friends.php',
                'Tests' => 'friends',
                'TestUser' => false,
                'Mandatory' => false
            ],
            [
                'Key' => 'better',
                'Title' => 'Better',
                'Target' => 'better.php',
                'Tests' => 'better',
                'TestUser' => false,
                'Mandatory' => false
            ],
            [
                'Key' => 'random',
                'Title' => 'Random Album',
                'Target' => 'random.php',
                'Tests' => 'random',
                'TestUser' => false,
                'Mandatory' => false
            ],
            [
                'Key' => 'logchecker',
                'Title' => 'Logchecker',
                'Target' => 'logchecker.php',
                'Tests' => 'logchecker',
                'TestUser' => false,
                'Mandatory' => false
            ],
            [
                'Key' => 'posts',
                'Title' => 'Posts',
                'Target' => 'userhistory.php?action=posts',
                'Tests' => 'userhistory,posts',
                'TestUser' => false,
                'Mandatory' => false
            ]
        ];

        $this->table('nav_items')->insert($fixtures)->save();

        $this->table('users_info')
             ->addColumn('NavItems', 'string', ['limit' => 200])
             ->save();

        $this->execute("
            UPDATE users_info
            SET NavItems = '1,2,3,4,5,6,7,8,9,10'");
    }

    public function down() {
        $this->table('users_info')
             ->removeColumn('NavItems')
             ->save();

        $this->table('nav_items')->drop()->update();
    }
}
