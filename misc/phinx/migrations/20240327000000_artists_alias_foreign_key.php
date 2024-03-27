<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ArtistsAliasForeignKey extends AbstractMigration {
    public function up(): void {
        $this->table('artists_group')
            ->addColumn('PrimaryAlias', 'integer', ['signed' => true, 'null' => true, 'default' => null])
            ->save();
        $this->execute("
            UPDATE artists_group
            INNER JOIN artists_alias aa ON (
                aa.ArtistID = artists_group.ArtistID
                  AND aa.Name = BINARY artists_group.Name
                  AND aa.Redirect = 0)
            SET PrimaryAlias = aa.AliasID
        ");
        // somewhat broken groups; if there are any rows left with PrimaryAlias = NULL those need to be fixed manually
        $this->execute("
            UPDATE artists_group
            INNER JOIN artists_alias aa ON (
                aa.ArtistID = artists_group.ArtistID
                  AND aa.Name = artists_group.Name
                  AND aa.Redirect = 0)
            SET PrimaryAlias = aa.AliasID
            WHERE PrimaryAlias IS NULL
        ");
        $this->table('artists_group')
            ->changeColumn('PrimaryAlias', 'integer', ['signed' => true, 'null' => false])
            ->removeColumn('Name')
            ->addForeignKey('PrimaryAlias', 'artists_alias', 'AliasID')
            ->save();
        $this->table('artists_alias')
            ->addForeignKey('ArtistID', 'artists_group', 'ArtistID')
            ->save();
    }

    public function down(): void {
        $this->table('artists_group')
            ->addColumn('Name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'collation' => 'utf8mb4_0900_ai_ci',
                'encoding' => 'utf8mb4',
            ])
            ->save();
        $this->execute("
            UPDATE artists_group
            INNER JOIN artists_alias aa ON (aa.AliasID = artists_group.PrimaryAlias)
            SET artists_group.Name = aa.Name
        ");
        $this->table('artists_group')
            ->changeColumn('Name', 'string', ['null' => false, 'default' => null])
            ->removeColumn('PrimaryAlias')
            ->save();
        $this->table('artists_alias')
            ->dropForeignKey('ArtistID')
            ->save();
    }
}
