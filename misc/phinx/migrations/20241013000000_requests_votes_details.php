<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RequestsVotesDetails extends AbstractMigration {
    public function up(): void {
        $this->table('requests_votes')
             ->changePrimaryKey(null)
             ->save();
        // phinx can't add an AUTO_INCREMENT column to an existing table https://github.com/cakephp/phinx/issues/1880
        $this->execute('ALTER TABLE `requests_votes` ADD `requests_votes_id` INT(11) AUTO_INCREMENT PRIMARY KEY FIRST');
        $this->table('requests_votes')
             ->changePrimaryKey('requests_votes_id')
             ->removeIndex('Bounty')
             ->addForeignKey('RequestID', 'requests', 'ID')
             ->addForeignKey('UserID', 'users_main', 'ID')
             ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
             ->save();
    }

    public function down(): void {
        $this->table('requests_votes')
             ->removeColumn('requests_votes_id')
             ->addIndex('Bounty')
             ->dropForeignKey('RequestID')
             ->dropForeignKey('UserID')
             ->removeColumn('created')
             ->changePrimaryKey(['RequestID', 'UserID'])
             ->save();
    }
}
