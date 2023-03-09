<?php

use Phinx\Migration\AbstractMigration;

/* If this migration fails, you will need to clean up your database,
 * by either dropping rows with null values for AliasTag and BadTag,
 * and/or ensuring all BadTag values are unique.
 *
 * SELECT badtag FROM tag_aliases GROUP BY badtag HAVING count(*) > 1;
 */

class UniqueBadTagAlias extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE tag_aliases
                DROP KEY BadTag,
                ADD UNIQUE KEY ta_bad_uidx (BadTag),
                MODIFY AliasTag varchar(100) NOT NULL,
                MODIFY BadTag varchar(100) NOT NULL
        ');
    }

    public function down(): void {
        $this->execute('
            ALTER TABLE tag_aliases
                DROP KEY ta_bad_uidx,
                ADD KEY BadTag (BadTag),
                MODIFY AliasTag varchar(30) NULL,
                MODIFY BadTag varchar(30) NULL
        ');
    }
}
