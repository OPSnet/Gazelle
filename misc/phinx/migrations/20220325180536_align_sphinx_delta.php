<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AlignSphinxDelta extends AbstractMigration {
    public function up(): void {
        $this->table('sphinx_delta')
            ->changeColumn('GroupName', 'string', ['length' => 300])
            ->changeColumn('Encoding', 'string', ['length' => 15, 'null' => true])
            ->changeColumn('Format', 'string', ['length' => 10, 'null' => true])
            ->changeColumn('Media', 'string', ['length' => 20])
            ->changeColumn('TagList', 'string', ['length' => 500])
            ->changeColumn('CatalogueNumber', 'string', ['length' => 80, 'null' => true])
            ->changeColumn('RecordLabel', 'string', ['length' => 80, 'null' => true])
            ->changeColumn('RemasterCatalogueNumber', 'string', ['length' => 80, 'null' => true])
            ->changeColumn('RemasterRecordLabel', 'string', ['length' => 80, 'null' => true])
            ->changeColumn('RemasterTitle', 'string', ['length' => 80, 'null' => true])
            ->changeColumn('RemasterYear', 'integer', ['null' => true])
            ->save();
    }

    public function down(): void {
        $this->table('sphinx_delta')
            ->changeColumn('GroupName', 'string', ['length' => 255])
            ->changeColumn('Encoding', 'string', ['length' => 255, 'null' => true])
            ->changeColumn('Format', 'string', ['length' => 255, 'null' => true])
            ->changeColumn('Media', 'string', ['length' => 255, 'null' => true])
            ->changeColumn('TagList', 'string', ['length' => 768, 'null' => true])
            ->changeColumn('CatalogueNumber', 'string', ['length' => 50, 'null' => true])
            ->changeColumn('RecordLabel', 'string', ['length' => 50, 'null' => true])
            ->changeColumn('RemasterCatalogueNumber', 'string', ['length' => 50, 'null' => true])
            ->changeColumn('RemasterRecordLabel', 'string', ['length' => 50, 'null' => true])
            ->changeColumn('RemasterTitle', 'string', ['length' => 512, 'null' => true])
            ->changeColumn('RemasterYear', 'string', ['length' => 50])
            ->save();
    }
}
