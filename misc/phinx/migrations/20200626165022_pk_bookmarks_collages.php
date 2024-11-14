<?php

use Phinx\Migration\AbstractMigration;

/* if this crashes and burns, you have duplicates in your
 * database that you will need to clean. The following
 * queries may help:

select UserID, CollageID from bookmarks_collages group by UserID, CollageID having count(*) > 1;
+--------+-----------+
| UserID | CollageID |
+--------+-----------+
|   9709 |       169 |
|   9709 |       777 |
+--------+-----------+

select * from bookmarks_collages where userid = 9709 and CollageID in (169, 777);
+--------+-----------+---------------------+
| UserID | CollageID | Time                |
+--------+-----------+---------------------+
|   9709 |       777 | 2016-12-25 13:41:11 |
|   9709 |       777 | 2016-12-25 13:41:11 |
|   9709 |       169 | 2017-06-01 12:23:47 |
|   9709 |       169 | 2017-06-01 12:23:47 |
+--------+-----------+---------------------+

begin;
delete from bookmarks_collages where userid = 9709 and collageid in (777, 169);
insert into bookmarks_collages values (9709, 777, '2016-12-25 13:41:11'), (9709, 169, '2017-06-01 12:23:47');

select UserID, CollageID from bookmarks_collages group by UserID, CollageID having count(*) > 1;
Empty set (0.022 sec)

commit;

 * Have fun, good luck.
 *
 */

class PkBookmarksCollages extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE bookmarks_collages
                ADD PRIMARY KEY (CollageID, UserID),
                DROP KEY CollageID
        ');
    }

    public function down(): void {
        $this->execute('
            ALTER TABLE bookmarks_collages
                DROP PRIMARY KEY,
                ADD KEY CollageID (CollageID)
        ');
    }
}
