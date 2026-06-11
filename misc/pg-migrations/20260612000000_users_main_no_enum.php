<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/* Defining Mysql enums as Postgresql enums prevents the Pg query planner
 * from pushing down the query to Mysql and allow Mysql to use an index on
 * Enabled. Defining it as char(1) instead of an enum allows the filtering
 * to take place. This opens up the possibility of Postgres updating the
 * field to a meaningless value (for example '4'). So avoid doing that.
 */

final class UsersMainNoEnum extends AbstractMigration {
    protected array $enum = [ /** @phpstan-ignore-line */
        ['users_main_enabled_t', "enum('0','1','2','unconfirmed','enabled','disabled','banned')"],
        ['users_main_visible_t', "enum('0','1','yes','no')"],
    ];

    public function up(): void {
        $MYSQLDB = MYSQL_DB;
        $this->query('
            drop foreign table relay.users_main
        ');
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

        foreach ($this->enum as $e) {
            $this->query("drop type {$e[0]}");
        }
    }

    public function down(): void {
        $MYSQLDB = MYSQL_DB;
        foreach ($this->enum as $e) {
            $this->query("
                create type {$e[0]} as {$e[1]}
            ");
        }

        $this->query('
            drop foreign table relay.users_main
        ');
        $this->query(<<<END_SQL
            create foreign table relay.users_main (
                "ID" integer not null,
                "Username" character varying(20) not null,
                "Email" character varying(255) not null,
                "PassHash" character varying(255) not null,
                "IRCKey" character(32),
                "IP" character varying(15) not null,
                title character varying(1024),
                "Enabled" public.users_main_enabled_t not null,
                "Paranoia" text,
                "Visible" public.users_main_visible_t not null,
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
