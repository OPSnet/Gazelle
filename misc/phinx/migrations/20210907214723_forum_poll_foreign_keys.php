<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumPollForeignKeys extends AbstractMigration
{
    public function up(): void
    {
        $this->table('forums_polls')
            ->changeColumn('TopicID', 'integer', ['signed' => true])
            ->addForeignKey('TopicID', 'forums_topics', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void
    {
        $this->table('forums_polls')
            ->dropForeignKey('TopicID')
            ->save();

        $this->table('forums_polls')
            ->changeColumn('TopicID', 'integer', ['signed' => false])
            ->save();
    }
}
