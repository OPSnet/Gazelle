<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Resource extends AbstractMigration {
    /**
     * @param array<string> $resourceList
     */
    public function valueList(array $resourceList): string {
        $list = '';
        foreach ($resourceList as $idx => $label) {
            if ($idx !== 0) {
                $list .= ', ';
            }
            $list .= "($idx, '$label')";
        }
        return $list;
    }

    public function up(): void {
        $this->table('encoding', ['id' => false, 'primary_key' => 'id_encoding'])
            ->addColumn('id_encoding', 'integer', ['identity' => true])
            ->addColumn('label', 'string', ['length' => 20])
            ->save();
        $this->query(
            "insert into encoding (id_encoding, label) values "
                . $this->valueList(ENCODING)
        );

        $this->table('format', ['id' => false, 'primary_key' => 'id_format'])
            ->addColumn('id_format', 'integer', ['identity' => true])
            ->addColumn('label', 'string', ['length' => 20])
            ->save();
        $this->query(
            "insert into format (id_format, label) values "
                . $this->valueList(FORMAT)
        );

        $this->table('media', ['id' => false, 'primary_key' => 'id_media'])
            ->addColumn('id_media', 'integer', ['identity' => true])
            ->addColumn('label', 'string', ['length' => 20])
            ->save();
        $this->query(
            "insert into media (id_media, label) values "
                . $this->valueList(MEDIA)
        );

        $this->table('category_has_encoding', ['id' => false, 'primary_key' => ['id_category', 'id_encoding']])
            ->addColumn('id_category', 'integer')
            ->addColumn('id_encoding', 'integer')
            ->addForeignKey('id_category', 'category', 'id_category')
            ->addForeignKey('id_encoding', 'encoding', 'id_encoding')
            ->save();
        $this->query("
            insert into category_has_encoding
            select (select id_category from category where name = 'Music'), id_encoding
            from encoding
        ");

        $this->table('category_has_format', ['id' => false, 'primary_key' => ['id_category', 'id_format']])
            ->addColumn('id_category', 'integer')
            ->addColumn('id_format', 'integer')
            ->addForeignKey('id_category', 'category', 'id_category')
            ->addForeignKey('id_format', 'format', 'id_format')
            ->save();
        $this->query("
            insert into category_has_format
            select (select id_category from category where name = 'Music'), id_format
            from format
        ");

        $this->table('category_has_media', ['id' => false, 'primary_key' => ['id_category', 'id_media']])
            ->addColumn('id_category', 'integer')
            ->addColumn('id_media', 'integer')
            ->addForeignKey('id_category', 'category', 'id_category')
            ->addForeignKey('id_media', 'media', 'id_media')
            ->save();
        $this->query("
            insert into category_has_media
            select (select id_category from category where name = 'Music'), id_media
            from media
        ");
    }

    public function down(): void {
        $this->table('category_has_media')->drop()->save();
        $this->table('category_has_format')->drop()->save();
        $this->table('category_has_encoding')->drop()->save();
        $this->table('media')->drop()->save();
        $this->table('format')->drop()->save();
        $this->table('encoding')->drop()->save();
    }
}
