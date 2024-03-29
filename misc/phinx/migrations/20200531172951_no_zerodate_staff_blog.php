<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateStaffBlog extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE staff_blog
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("ALTER TABLE staff_blog_visits
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE staff_blog
            MODIFY Time datetime
        ");
        $this->execute("ALTER TABLE staff_blog_visits
            MODIFY Time datetime
        ");
    }
}
