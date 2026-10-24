<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserJsonParanoia extends AbstractMigration {
    public function up(): void {
        $MYSQLDB = MYSQL_DB;
        $this->query("
            drop foreign table if exists relay.users_main
        ");
        $this->query(<<<END_SQL
            create foreign table relay.users_main (
                "ID" integer not null,
                "Username" character varying(20) not null,
                "Email" character varying(255) not null,
                "PassHash" character varying(255) not null,
                "IRCKey" character(32),
                "IP" character varying(15) not null,
                title character varying(1024),
                "Enabled" character(1) not null, -- actually an enum in Mysql
                "Paranoia" text,
                "Visible" character(1) not null, -- actually an enum in Mysql
                "Invites" integer not null,
                "PermissionID" integer not null,
                "CustomPermissions" text,
                can_leech smallint not null,
                torrent_pass character(32) not null,
                "RequiredRatio" double precision not null,
                ipcc character varying(2) not null,
                created timestamp without time zone not null,
                updated timestamp without time zone not null,
                auth_key character varying(32) not null,
                avatar character varying(255) not null,
                inviter_user_id integer not null,
                profile_info text not null,
                profile_title character varying(255) not null,
                slogan character varying(255),
                stylesheet_id integer not null,
                stylesheet_url character varying(255),
                nav_list json,
                paranoia_v2 json
            )
            server relayer
            options (
                dbname '$MYSQLDB',
                table_name 'users_main'
            )
            END_SQL
        );
    }

    public function down(): void {
        $MYSQLDB = MYSQL_DB;
        $this->query("
            drop foreign table if exists relay.users_main
        ");
        $this->query(<<<END_SQL
            create foreign table relay.users_main (
                "ID" integer not null,
                "Username" character varying(20) not null,
                "Email" character varying(255) not null,
                "PassHash" character varying(255) not null,
                "IRCKey" character(32),
                "IP" character varying(15) not null,
                title character varying(1024),
                "Enabled" character(1) not null, -- actually an enum in Mysql
                "Paranoia" text,
                "Visible" character(1) not null, -- actually an enum in Mysql
                "Invites" integer not null,
                "PermissionID" integer not null,
                "CustomPermissions" text,
                can_leech smallint not null,
                torrent_pass character(32) not null,
                "RequiredRatio" double precision not null,
                ipcc character varying(2) not null,
                created timestamp without time zone not null,
                updated timestamp without time zone not null,
                auth_key character varying(32) not null,
                avatar character varying(255) not null,
                inviter_user_id integer not null,
                profile_info text not null,
                profile_title character varying(255) not null,
                slogan character varying(255),
                stylesheet_id integer not null,
                stylesheet_url character varying(255),
                nav_list json
            )
            server relayer
            options (
                dbname '$MYSQLDB',
                table_name 'users_main'
            )
            END_SQL
        );
    }
}
