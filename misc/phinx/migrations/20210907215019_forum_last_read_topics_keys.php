<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumLastReadTopicsKeys extends AbstractMigration
{
    public function up(): void {
        $this->table('forums_last_read_topics')
            ->changeColumn('UserID', 'integer', ['signed' => false])
            ->addForeignKey('PostID', 'forums_posts', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('TopicID', 'forums_topics', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('forums_last_read_topics')
            ->dropForeignKey('PostID')
            ->dropForeignKey('TopicID')
            ->dropForeignKey('UserID')
            ->save();

        $this->table('forums_last_read_topics')
            ->changeColumn('UserID', 'integer', ['signed' => true])
            ->save();
    }
}
