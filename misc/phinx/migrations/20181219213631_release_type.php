<?php

use Phinx\Migration\AbstractMigration;

class ReleaseType extends AbstractMigration {
    public function up(): void {
        $this->table('release_type', ['id' => false, 'primary_key' => 'ID'])
                      ->addColumn('ID', 'integer', ['limit' => 10, 'identity' => true])
                      ->addColumn('Name', 'string', ['limit' => 50])
                      ->addIndex(['Name'], ['unique' => true])
                      ->create();

        $data = [
            ['ID' =>  1, 'Name' => 'Album'],
            ['ID' =>  3, 'Name' => 'Soundtrack'],
            ['ID' =>  5, 'Name' => 'EP'],
            ['ID' =>  6, 'Name' => 'Anthology'],
            ['ID' =>  7, 'Name' => 'Compilation'],
            ['ID' =>  8, 'Name' => 'Sampler'],
            ['ID' =>  9, 'Name' => 'Single'],
            ['ID' => 10, 'Name' => 'Demo'],
            ['ID' => 11, 'Name' => 'Live album'],
            ['ID' => 12, 'Name' => 'Split'],
            ['ID' => 13, 'Name' => 'Remix'],
            ['ID' => 14, 'Name' => 'Bootleg'],
            ['ID' => 15, 'Name' => 'Interview'],
            ['ID' => 16, 'Name' => 'Mixtape'],
            ['ID' => 21, 'Name' => 'Unknown']
        ];

        $this->table('release_type')->insert($data)->update();
    }

    public function down(): void {
        $this->table('release_type')->drop()->update();
    }
}
