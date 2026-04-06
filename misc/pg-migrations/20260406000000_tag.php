<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class Tag extends AbstractMigration {
    public function up(): void {
        $this->query("create type tag_kind_t as enum ('genre', 'other')");

        $this->table('tag', ['id' => false, 'primary_key' => 'id_tag'])
            ->addColumn('id_tag', 'integer', ['identity' => true])
            ->addColumn('id_user', 'integer')
            ->addColumn('usage', 'integer', ['default' => 0])
            ->addColumn('name', 'string', ['length' => 100])
            ->addColumn('kind', Literal::from('tag_kind_t'))
            ->addIndex('name', ['name' => 'tag_n_uidx', 'unique' => true])
            ->addIndex('kind', ['name' => 'tag_k_idx'])
            ->addIndex('usage', ['name' => 'tag_u_idx'])
            ->save();

        $this->table('tag_alias', ['id' => false, 'primary_key' => 'id_tag_alias'])
            ->addColumn('id_tag_alias', 'integer', ['identity' => true])
            ->addColumn('alias_deny', 'string', ['length' => 100])
            ->addColumn('alias_allow', 'string', ['length' => 100])
            ->save();
    }

    public function down(): void {
        $this->table('tag_alias')->drop()->save();
        $this->table('tag')->drop()->save();
        $this->query('drop type tag_kind_t');
    }
}
