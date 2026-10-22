<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumPoll extends AbstractMigration {
    public function up(): void {
        $this->table('forum_poll', ['id' => false, 'primary_key' => 'id_forum_thread'])
            ->addColumn('id_forum_thread', 'integer', ['identity' => true])
            ->addColumn('created',         'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('is_featured',     'boolean', ['default' => false])
            ->addColumn('is_closed',       'boolean', ['default' => false])
            ->addColumn('question',        'text')
            ->addColumn('answer',          'jsonb')
            ->save();

        $this->table('forum_poll_vote', ['id' => false, 'primary_key' => ['id_forum_thread', 'id_user']])
            ->addColumn('id_forum_thread', 'integer')
            ->addColumn('id_user',         'integer')
            ->addColumn('vote',            'smallinteger')
            ->addForeignKey('id_forum_thread', 'forum_poll', 'id_forum_thread',
                ['delete' => 'cascade', 'update' => 'cascade']
            )
            ->save();
    }

    public function down(): void {
        $this->table('forum_poll_vote')->drop()->save();
        $this->table('forum_poll')->drop()->save();
    }
}
