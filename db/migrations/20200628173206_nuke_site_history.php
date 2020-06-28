<?php

use Phinx\Migration\AbstractMigration;

class NukeSiteHistory extends AbstractMigration {

    public function up() {
        $this->table('site_history')->drop()->update();
    }

    public function down() {
        $this->execute("
            CREATE TABLE `site_history` (
                `ID` int(10) NOT NULL AUTO_INCREMENT,
                `Title` varchar(255) DEFAULT NULL,
                `Url` varchar(255) NOT NULL DEFAULT '',
                `Category` tinyint(2) DEFAULT NULL,
                `SubCategory` tinyint(2) DEFAULT NULL,
                `Tags` mediumtext DEFAULT NULL,
                `AddedBy` int(10) DEFAULT NULL,
                `Date` datetime DEFAULT NULL,
                `Body` mediumtext DEFAULT NULL,
                PRIMARY KEY (`ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }
}
