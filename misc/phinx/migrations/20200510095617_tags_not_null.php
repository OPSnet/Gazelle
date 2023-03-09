<?php

use Phinx\Migration\AbstractMigration;

/* If this migration fails, you will need to clean up your database,
 * by either dropping rows with null values for Name or entering
 * a value.
 */

class TagsNotNull extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE tags MODIFY Name varchar(100) NOT NULL
        ');
    }
    public function down(): void {
        $this->execute('
            ALTER TABLE tags MODIFY Name varchar(100) NULL
        ');
    }
}
