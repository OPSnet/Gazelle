<?php

use Phinx\Migration\AbstractMigration;

class ExtendUserTitle extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE users_main
            MODIFY title varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE users_main
            MODIFY title varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci
        ");
    }
}
