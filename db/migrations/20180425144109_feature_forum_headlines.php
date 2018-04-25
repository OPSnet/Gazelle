<?php


use Phinx\Migration\AbstractMigration;

class FeatureForumHeadlines extends AbstractMigration
{
    public function change()
    {
    	$this->table('forums')
		    ->addColumn('IsHeadline', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
		    ->update();

    	$this->table('forums_topics')
		    ->addIndex('LastPostTime')
		    ->update();
    }
}
