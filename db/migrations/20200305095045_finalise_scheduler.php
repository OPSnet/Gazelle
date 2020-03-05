<?php

use Phinx\Migration\AbstractMigration;

class FinaliseScheduler extends AbstractMigration
{
    public function up()
    {
        $this->table('periodic_task')
             ->insert([
                 [
                     'name' => 'Ratio Watch - Torrent History',
                     'classname' => 'TorrentHistory',
                     'description' => 'Calculates seeding torrent counts',
                     'period' => 60 * 60
                 ],
                 [
                     'name' => 'Ratio Watch - Required Ratio',
                     'classname' => 'RatioRequirements',
                     'description' => 'Calculates required ratios',
                     'period' => 60 * 60 * 24
                 ],
                 [
                     'name' => 'Demote Users by Ratio',
                     'classname' => 'DemoteUsersRatio',
                     'description' => 'Demotes users with insufficient ratio',
                     'period' => 60 * 60 * 24
                 ]
             ])
             ->save();
    }

    public function down()
    {
        $builder = $this->getQueryBuilder();
        $builder->delete('periodic_task')
                ->where(function ($exp) {
                    return $exp->in('classname', ['TorrentHistory', 'RatioRequirements', 'DemoteUsersRatio']);
                })
                ->execute();
    }
}
