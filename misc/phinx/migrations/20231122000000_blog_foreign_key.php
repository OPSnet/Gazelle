<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BlogForeignKey extends AbstractMigration {
    public function change(): void {
        $this->table('user_read_blog')
            ->dropForeignKey('blog_id')
            ->addForeignKey('blog_id', 'blog', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('user_read_news')
            ->dropForeignKey('news_id')
            ->addForeignKey('news_id', 'news', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }
}
