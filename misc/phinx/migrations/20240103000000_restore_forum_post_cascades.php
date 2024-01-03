<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RestoreForumPostCascades extends AbstractMigration {
    public function up(): void {
        $this->table('forums_posts')
            ->dropForeignKey('TopicID')
            ->addForeignKey('TopicID', 'forums_topics', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('forums_posts')
            ->dropForeignKey('TopicID')
            ->addForeignKey('TopicID', 'forums_topics', 'ID')
            ->save();
    }
}
