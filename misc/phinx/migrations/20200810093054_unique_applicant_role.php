<?php

use Phinx\Migration\AbstractMigration;

class UniqueApplicantRole extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE applicant_role ADD UNIQUE KEY ar_title_uidx (Title)
        ');
    }

    public function down(): void {
        $this->execute('
            ALTER TABLE applicant_role DROP KEY ar_title_uidx
        ');
    }
}
