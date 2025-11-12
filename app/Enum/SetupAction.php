<?php

namespace Gazelle\Enum;

enum SetupAction: string {
    case PG_RW_CONN = "postgres connection (read-write)";
    case PG_RW_TABLES = "postgres tables initialized (read-write)";
    case PG_RW_MY_RELAY = "postgres mysql_fdw relay (read-write)";
    case PG_RO_CONN = "postgres connection (read-only)";
    case PG_RO_TABLES = "postgres tables initialized (read-only)";
    case PG_RO_MY_RELAY = "postgres mysql_fdw relay (read-only)";
    case MY_CONN = "mysql connection";
    case MY_TABLES = "mysql tables initialized";
    case SPHINX = "sphinx server";
    case TWIG = "twig templates";
}
