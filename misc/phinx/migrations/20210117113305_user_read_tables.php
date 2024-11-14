<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserReadTables extends AbstractMigration
{
    public function change(): void {
        $this->table('user_read_blog', ['id' => false, 'primary_key' => 'user_id'])
            ->addColumn('user_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('blog_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('blog_id', 'blog',       'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('user_read_forum', ['id' => false, 'primary_key' => 'user_id'])
            ->addColumn('user_id',   'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('last_read', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('user_read_news', ['id' => false, 'primary_key' => 'user_id'])
            ->addColumn('user_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('news_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('news_id', 'news',       'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
