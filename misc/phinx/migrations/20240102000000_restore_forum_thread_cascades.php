<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RestoreForumThreadCascades extends AbstractMigration {
    public function up(): void {
        $this->table('forums_topics')
            ->dropForeignKey('AuthorID')
            ->dropForeignKey('ForumID')
            ->dropForeignKey('LastPostAuthorID')
            ->dropForeignKey('LastPostID')
            ->addForeignKey('AuthorID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('ForumID', 'forums', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('LastPostAuthorID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('LastPostID', 'forums_posts', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('forums_topics')
            ->dropForeignKey('AuthorID')
            ->dropForeignKey('ForumID')
            ->dropForeignKey('LastPostAuthorID')
            ->dropForeignKey('LastPostID')
            ->addForeignKey('AuthorID', 'users_main', 'ID')
            ->addForeignKey('ForumID', 'forums', 'ID')
            ->addForeignKey('LastPostAuthorID', 'users_main', 'ID')
            ->addForeignKey('LastPostID', 'forums_posts', 'ID')
            ->save();
    }
}
