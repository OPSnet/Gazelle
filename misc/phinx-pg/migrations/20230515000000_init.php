<?php

use Phinx\Migration\AbstractMigration;

final class Init extends AbstractMigration
{
    public function up(): void {
        $this->execute('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->execute('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->execute('CREATE SCHEMA geo authorization CURRENT_ROLE');
        $this->execute("CREATE TABLE geo.asn (
            id_asn bigint not null primary key,
            cc character(2) not null,
            name text not null,
            name_ts tsvector generated always as (to_tsvector('simple', name)) stored
        );");
        $this->execute('CREATE INDEX asn_ts_name_idx ON geo.asn USING gin (name_ts)');
        $this->execute('CREATE TABLE geo.asn_network (
            id_asn BIGINT NOT NULL,
            network CIDR NOT NULL,
            created DATE NOT NULL DEFAULT CURRENT_DATE,
            FOREIGN KEY (id_asn) REFERENCES geo.asn(id_asn) ON DELETE CASCADE
        )');
        $this->execute('CREATE INDEX asn_network_network_idx ON geo.asn_network USING gist (network)');
        $this->execute('CREATE TABLE geo.ptr (
            id_ptr SERIAL NOT NULL PRIMARY KEY,
            ipv4 INET NOT NULL,
            name TEXT NOT NULL,
            created DATE NOT NULL DEFAULT CURRENT_DATE
        )');
        $this->execute('CREATE INDEX ptr_ipv4_idx ON geo.ptr USING gist (ipv4);');
        $this->execute("CREATE TABLE geo.asn_trg AS SELECT word FROM ts_stat('select to_tsvector(''simple'', name) FROM geo.asn');");
        $this->execute('CREATE INDEX asn_trg_idx ON geo.asn_trg USING gin (word gin_trgm_ops);');
    }
    public function down(): void {
        $this->table('geo.asn_trg')->drop()->save();
        $this->table('geo.ptr')->drop()->save();
        $this->table('geo.asn_network')->drop()->save();
        $this->table('geo.asn')->drop()->save();
        $this->execute('DROP SCHEMA IF EXISTS geo CASCADE');
        $this->execute('DROP EXTENSION IF EXISTS pg_trgm');
    }
}
