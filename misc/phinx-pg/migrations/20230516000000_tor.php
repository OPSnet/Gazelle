<?php

use Phinx\Migration\AbstractMigration;

class Tor extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE tor_node (
                id_tor_node SERIAL PRIMARY KEY,
                ipv4 INET NOT NULL,
                created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
        $this->execute('CREATE INDEX tn_ipv4_idx ON tor_node USING GIST (ipv4)');
    }

    public function down(): void {
        $this->table('tor_node')->drop()->save();
    }
}
