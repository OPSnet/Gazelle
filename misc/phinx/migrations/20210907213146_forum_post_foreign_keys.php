<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumPostForeignKeys extends AbstractMigration
{
    public function up(): void
    {
        $this->table('forums_posts')
            ->changeColumn('AuthorID', 'integer', ['signed' => false])
            ->changeColumn('EditedUserID', 'integer', ['signed' => false])
            ->addForeignKey('TopicID', 'forums_topics', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void
    {
        $this->table('forums_posts')
            ->dropForeignKey('AuthorID')
            ->dropForeignKey('TopicID')
            ->save();

        $this->table('forums_posts')
            ->changeColumn('AuthorID', 'integer', ['signed' => true])
            ->changeColumn('EditedUserID', 'integer', ['signed' => true])
            ->save();
    }
}
