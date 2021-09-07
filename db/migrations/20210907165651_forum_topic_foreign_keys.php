<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumTopicForeignKeys extends AbstractMigration
{
    public function up(): void
    {
        $this->table('forums_topics')
            ->changeColumn('ForumID', 'integer', ['signed' => false])
            ->changeColumn('AuthorID', 'integer', ['signed' => false])
            ->changeColumn('LastPostAuthorID', 'integer', ['signed' => false])
            ->addForeignKey('AuthorID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('LastPostAuthorID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('ForumID', 'forums', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('LastPostID', 'forums_posts', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void
    {
        $this->table('forums_topics')
            ->dropForeignKey('ForumID')
            ->dropForeignKey('AuthorID')
            ->dropForeignKey('LastPostAuthorID')
            ->save();

        $this->table('forums_topics')
            ->changeColumn('ForumID', 'integer', ['signed' => true])
            ->changeColumn('AuthorID', 'integer', ['signed' => true])
            ->changeColumn('LastPostAuthorID', 'integer', ['signed' => true])
            ->save();
    }
}
