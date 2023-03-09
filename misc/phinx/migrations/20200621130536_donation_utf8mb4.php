<?php

use Phinx\Migration\AbstractMigration;

class DonationUtf8mb4 extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE donor_rewards DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");
    }

    public function down(): void {
        $this->execute("ALTER TABLE donor_rewards DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
    }
}
