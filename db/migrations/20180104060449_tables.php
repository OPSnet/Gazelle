<?php


use Phinx\Migration\AbstractMigration;

class Tables extends AbstractMigration {
	/**
	 * TODO: Migrate from gazelle.sql to a proper change() method
	 */
    public function up() {
    	$this->execute(file_get_contents(__DIR__.'/../data/gazelle.sql'));
    	$this->execute(file_get_contents(__DIR__.'/../data/data.sql'));
    }
}
