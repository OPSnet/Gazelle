<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumCategoryPK extends AbstractMigration {
    public function change(): void {
        $this->query("
            ALTER TABLE forums_categories MODIFY ID tinyint not null auto_increment
        ");
    }
}
