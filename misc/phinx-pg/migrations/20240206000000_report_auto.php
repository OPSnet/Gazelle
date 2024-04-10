<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ReportAuto extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE report_auto_category (
            id_report_auto_category integer primary key generated always as identity,
            name varchar(20) not null unique check(name ~* \'^[a-z][a-z0-9]*$\')
        )');
        $this->execute('CREATE TABLE report_auto_type (
            id_report_auto_type integer primary key generated always as identity,
            id_report_auto_category integer default null,
            name varchar(40) not null unique,
            description text not null check(length(description) <= 2000),
            CONSTRAINT fk_id_report_auto_category
              FOREIGN KEY(id_report_auto_category)
              REFERENCES report_auto_category(id_report_auto_category)
              ON DELETE SET NULL
        )');
        $this->execute('CREATE TABLE report_auto (
            id_report_auto integer primary key generated always as identity,
            id_user integer not null,
            id_report_auto_type integer not null,
            created timestamptz not null default now(),
            resolved timestamptz default null,
            id_owner integer default null,
            data JSONB not null,
            CONSTRAINT fk_id_report_auto_type
              FOREIGN KEY(id_report_auto_type)
              REFERENCES report_auto_type(id_report_auto_type)
              ON DELETE CASCADE
        )');
        $this->execute('CREATE INDEX idx_not_resolved ON report_auto (id_user) WHERE resolved IS NULL;');
        $this->execute('CREATE TABLE report_auto_comment (
            id_report_auto_comment integer primary key generated always as identity,
            id_report_auto integer not null,
            id_user integer not null,
            created timestamptz not null default now(),
            comment text default null,
            CONSTRAINT fk_id_report_auto
              FOREIGN KEY(id_report_auto)
              REFERENCES report_auto(id_report_auto)
              ON DELETE CASCADE
        )');
    }

    public function down(): void {
        $this->table('report_auto_comment')->drop()->save();
        $this->table('report_auto')->drop()->save();
        $this->table('report_auto_type')->drop()->save();
        $this->table('report_auto_category')->drop()->save();
    }
}
