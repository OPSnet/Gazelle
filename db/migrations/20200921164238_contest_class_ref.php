<?php

use Phinx\Migration\AbstractMigration;

class ContestClassRef extends AbstractMigration {
    public function up() {
        $this->execute("
            ALTER TABLE bonus_pool_contrib DROP FOREIGN KEY /* IF EXISTS */ bonus_pool_contrib_ibfk_1;
            ALTER TABLE bonus_pool_contrib DROP FOREIGN KEY /* IF EXISTS */ bonus_pool_contrib_ibfk_2;
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool DROP FOREIGN KEY /* IF EXISTS */ contest_has_bonus_pool_ibfk_1;
            ALTER TABLE contest_has_bonus_pool DROP FOREIGN KEY /* IF EXISTS */ contest_has_bonus_pool_ibfk_2;
        ");
        $this->execute("
            ALTER TABLE bonus_pool
                CHANGE COLUMN ID bonus_pool_id int(6) unsigned NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN Name name varchar(80) NOT NULL,
                CHANGE COLUMN SinceDate since_date datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                CHANGE COLUMN UntilDate until_date datetime NOT NULL,
                CHANGE COLUMN Total total float NOT NULL DEFAULT 0
        ");
        $this->execute("
            ALTER TABLE bonus_pool_contrib
                CHANGE COLUMN ID bonus_pool_contrib_id int(6) unsigned NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN BonusPoolID bonus_pool_id int(6) unsigned NOT NULL,
                CHANGE COLUMN UserID user_id int(10) unsigned NOT NULL,
                CHANGE COLUMN AmountRecv amount_recv float NOT NULL,
                CHANGE COLUMN AmountSent amount_sent float NOT NULL,
                CHANGE COLUMN Created created datetime NOT NULL DEFAULT current_timestamp()
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool
                CHANGE COLUMN BonusPoolID bonus_pool_id int(6) unsigned NOT NULL,
                CHANGE COLUMN ContestID contest_id int(11) NOT NULL,
                CHANGE COLUMN `Status` `status` enum('open','ready','paid') NOT NULL DEFAULT 'open',
                ADD COLUMN bonus_contest int(4) unsigned NOT NULL DEFAULT 15,
                ADD COLUMN bonus_user int(4) unsigned NOT NULL DEFAULT 5,
                ADD COLUMN bonus_per_entry int(4) unsigned NOT NULL DEFAULT 80
        ");
        $this->execute("
            ALTER TABLE bonus_pool_contrib
                ADD CONSTRAINT bonus_pool_contrib_ibfk_1 FOREIGN KEY (bonus_pool_id) REFERENCES bonus_pool (bonus_pool_id),
                ADD CONSTRAINT bonus_pool_contrib_ibfk_2 FOREIGN KEY (user_id) REFERENCES users_main (ID)
        ");
        $this->execute("
            ALTER TABLE contest DROP FOREIGN KEY /* IF EXISTS */ contest_type_fk;
            ALTER TABLE contest_leaderboard DROP FOREIGN KEY /* IF EXISTS */ contest_leaderboard_fk;
        ");
        $this->execute("
            ALTER TABLE contest_leaderboard
                CHANGE COLUMN ContestID contest_id int(11) NOT NULL,
                CHANGE COLUMN UserID user_id int(11) NOT NULL,
                CHANGE COLUMN FlacCount entry_count int(11) NOT NULL,
                CHANGE COLUMN LastTorrentID last_entry_id int(11) NOT NULL,
                DROP COLUMN LastTorrentName,
                DROP COLUMN LastUpload,
                DROP COLUMN ArtistList,
                DROP COLUMN ArtistNames
        ");
        $this->execute("
            ALTER TABLE contest
                CHANGE COLUMN ID contest_id int(11) NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN Name name varchar(80) CHARACTER SET utf8mb4 NOT NULL,
                CHANGE COLUMN DateBegin date_begin datetime NOT NULL,
                CHANGE COLUMN DateEnd date_end datetime NOT NULL,
                CHANGE COLUMN Display display int(11) NOT NULL DEFAULT 50,
                CHANGE COLUMN MaxTracked max_tracked int(11) NOT NULL DEFAULT 500,
                CHANGE COLUMN ContestTypeID contest_type_id int(11) NOT NULL,
                CHANGE COLUMN Banner banner varchar(128) CHARACTER SET ascii DEFAULT NULL,
                CHANGE COLUMN WikiText description mediumtext DEFAULT NULL
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool
                ADD CONSTRAINT contest_has_bonus_pool_ibfk_1 FOREIGN KEY (bonus_pool_id) REFERENCES bonus_pool (bonus_pool_id),
                ADD CONSTRAINT contest_has_bonus_pool_ibfk_2 FOREIGN KEY (contest_id) REFERENCES contest(contest_id);
        ");
        $this->execute("
            ALTER TABLE contest_leaderboard ADD CONSTRAINT contest_leaderboard_fk FOREIGN KEY (contest_id) REFERENCES contest(contest_id);
        ");
        $this->execute("
            ALTER TABLE contest_type
                CHANGE COLUMN ID contest_type_id int(11) NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN Name name varchar(32) CHARACTER SET ascii NOT NULL;
            UPDATE contest_type SET name = replace(name, '_', '-');
        ");
        $this->execute("
            ALTER TABLE contest ADD CONSTRAINT contest_type_fk FOREIGN KEY (contest_type_id) REFERENCES contest_type(contest_type_id);
        ");
    }

    public function down() {
        $this->execute("
            ALTER TABLE bonus_pool_contrib DROP FOREIGN KEY /* IF EXISTS */ bonus_pool_contrib_ibfk_1;
            ALTER TABLE bonus_pool_contrib DROP FOREIGN KEY /* IF EXISTS */ bonus_pool_contrib_ibfk_2;
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool DROP FOREIGN KEY /* IF EXISTS */ contest_has_bonus_pool_ibfk_1;
            ALTER TABLE contest_has_bonus_pool DROP FOREIGN KEY /* IF EXISTS */ contest_has_bonus_pool_ibfk_2;
        ");
        $this->execute("
            ALTER TABLE bonus_pool
                CHANGE COLUMN bonus_pool_id ID int(6) unsigned NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN name Name varchar(80) NOT NULL,
                CHANGE COLUMN since_date SinceDate datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                CHANGE COLUMN until_date UntilDate datetime NOT NULL,
                CHANGE COLUMN total Total float NOT NULL DEFAULT 0
        ");
        $this->execute("
            ALTER TABLE bonus_pool_contrib
                CHANGE COLUMN bonus_pool_contrib_id ID int(6) unsigned NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN bonus_pool_id BonusPoolID int(6) unsigned NOT NULL,
                CHANGE COLUMN user_id UserID int(10) unsigned NOT NULL,
                CHANGE COLUMN amount_recv AmountRecv float NOT NULL,
                CHANGE COLUMN amount_sent AmountSent float NOT NULL,
                CHANGE COLUMN created Created timestamp NOT NULL DEFAULT current_timestamp()
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool
                CHANGE COLUMN bonus_pool_id BonusPoolID int(6) unsigned NOT NULL,
                CHANGE COLUMN contest_id ContestID int(11) NOT NULL,
                CHANGE COLUMN `status` `Status` enum('open','ready','paid') NOT NULL DEFAULT 'open',
                DROP COLUMN bonus_contest,
                DROP COLUMN bonus_user,
                DROP COLUMN bonus_per_entry
        ");
        $this->execute("
            ALTER TABLE bonus_pool_contrib
                ADD CONSTRAINT bonus_pool_contrib_ibfk_1 FOREIGN KEY (BonusPoolID) REFERENCES bonus_pool (ID),
                ADD CONSTRAINT bonus_pool_contrib_ibfk_2 FOREIGN KEY (UserID) REFERENCES users_main (ID)
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool ADD CONSTRAINT contest_has_bonus_pool_ibfk_1 FOREIGN KEY (BonusPoolID) REFERENCES bonus_pool (ID);
        ");
        $this->execute("
            ALTER TABLE contest DROP FOREIGN KEY /* IF EXISTS */ contest_type_fk;
            ALTER TABLE contest_leaderboard DROP FOREIGN KEY /* IF EXISTS */ contest_leaderboard_fk;
        ");
        $this->execute("
            ALTER TABLE contest_leaderboard
                CHANGE COLUMN contest_id ContestID int(11) NOT NULL,
                CHANGE COLUMN user_id UserID int(11) NOT NULL,
                CHANGE COLUMN entry_count FlacCount int(11) NOT NULL,
                CHANGE COLUMN last_entry_id LastTorrentID int(11) NOT NULL,
                ADD COLUMN LastTorrentName varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
                ADD COLUMN ArtistList varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
                ADD COLUMN ArtistNames varchar(200) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
                ADD COLUMN LastUpload datetime NOT NULL
        ");
        $this->execute("
            ALTER TABLE contest
                CHANGE COLUMN contest_id ID int(11) NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN name Name varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
                CHANGE COLUMN date_begin DateBegin datetime NOT NULL,
                CHANGE COLUMN date_end DateEnd datetime NOT NULL,
                CHANGE COLUMN display Display int(11) NOT NULL DEFAULT 50,
                CHANGE COLUMN max_tracked MaxTracked int(11) NOT NULL DEFAULT 500,
                CHANGE COLUMN contest_type_id ContestTypeID int(11) NOT NULL,
                CHANGE COLUMN banner Banner varchar(128) DEFAULT NULL,
                CHANGE COLUMN description WikiText mediumtext DEFAULT NULL
        ");
        $this->execute("
            ALTER TABLE contest_has_bonus_pool ADD CONSTRAINT contest_has_bonus_pool_ibfk_2 FOREIGN KEY (ContestID) REFERENCES contest(ID);
            ALTER TABLE contest_leaderboard ADD CONSTRAINT contest_leaderboard_fk FOREIGN KEY (ContestID) REFERENCES contest(ID);
        ");
        $this->execute("
            ALTER TABLE contest_type
                CHANGE COLUMN contest_type_id ID int(11) NOT NULL AUTO_INCREMENT,
                CHANGE COLUMN name Name varchar(32) CHARACTER SET ascii NOT NULL;
            UPDATE contest_type SET Name = replace(Name, '-', '_');
        ");
        $this->execute("
            ALTER TABLE contest ADD CONSTRAINT contest_type_fk FOREIGN KEY (ContestTypeID) REFERENCES contest_type(ID);
        ");
    }
}
