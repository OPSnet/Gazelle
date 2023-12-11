<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumCategoryInt extends AbstractMigration {
    public function up(): void {
        $this->query("
            ALTER TABLE forums_categories MODIFY ID int not null auto_increment
        ");
        $this->query("
            ALTER TABLE forums MODIFY CategoryID int not null
        ");
    }

    public function down(): void {
        $this->query("
            ALTER TABLE forums MODIFY CategoryID tinyint not null
        ");
        $this->query("
            ALTER TABLE forums_categories MODIFY ID tinyint not null auto_increment
        ");
    }
}
