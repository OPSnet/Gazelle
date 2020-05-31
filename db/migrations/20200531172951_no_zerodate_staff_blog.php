<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateStaffBlog extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE staff_blog
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("UPDATE staff_blog SET Time = now() WHERE Time = '0000-00-00 00:00:00'");
        $this->execute("ALTER TABLE staff_blog_visits
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("UPDATE staff_blog_visits SET Time = now() WHERE Time = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE staff_blog
            MODIFY Time datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
        $this->execute("ALTER TABLE staff_blog_visits
            MODIFY Time datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}
