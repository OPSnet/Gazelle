<?php

namespace Gazelle\Util;
use Gazelle\DB;
use Gazelle\Enum\SetupAction;
use Gazelle\Manager\User;

/**
 * performs some very basic tests to identify setup problems
 */
class SetupStatus {
    public static function checkPostgresRw(): array {
        $result = static::checkPostgres(PG_RW_DSN);
        return [
            [SetupAction::PG_RW_CONN, $result[0]],
            [SetupAction::PG_RW_TABLES, $result[1]],
            [SetupAction::PG_RW_MY_RELAY, $result[2]],
        ];
    }

    public static function checkPostgresRo(): array {
        $result = static::checkPostgres(PG_RO_DSN);
        return [
            [SetupAction::PG_RO_CONN, $result[0]],
            [SetupAction::PG_RO_TABLES, $result[1]],
            [SetupAction::PG_RO_MY_RELAY, $result[2]],
        ];
    }

    public static function checkMysql(): array {
        $my = DB::DB();
        $result = [null, null];  // conn, tables
        $current = 0;
        try {
            if ($my->scalar('select 1') !== 1) {
                $result[$current] = "basic select operation failed";
                return $result;
            }

            ++$current;
            if ($my->scalar('select count(*) from permissions') < 1) {
                $result[$current] = "tables not initialized";
            }
        } catch (\Exception $e) {
            $result[$current] = $e->getMessage();
        }
        return [
            [SetupAction::MY_CONN, $result[0]],
            [SetupAction::MY_TABLES, $result[1]],
        ];
    }

    public static function checkSphinx(): array {
        $spx = new \SphinxqlQuery();
        $spx->select('id')->from('torrents');

        $result = null;
        try {
            if (($ret = $spx->sphinxquery()) === false) {
                $result = "basic query failed";
            } elseif ($ret->Error) {
                $result = $ret->Error;
            }
        } catch (\Exception $e) {
            $result = $e->getMessage();
        }
        return [[SetupAction::SPHINX, $result]];
    }

    public static function checkTwig(): array {
        $result = null;
        try {
            $twig = Twig::factory(new User());
            if ($twig->render('index/public.twig', ['new' => true]) === '') {
                $result = "twig template returned empty string";
            }
        } catch (\Exception $e) {
            $result = $e->getMessage();
        }
        return [[SetupAction::TWIG, $result]];
    }

    /**
     * @return array of two-item arrays [SetupAction, null|string] where string contains an error msg
     *     encountered in the corresponding SetupAction
     */
    public static function check(): array {
        $result = [];
        array_push($result, ...static::checkPostgresRw());
        array_push($result, ...static::checkPostgresRo());
        array_push($result, ...static::checkMysql());
        array_push($result, ...static::checkSphinx());
        array_push($result, ...static::checkTwig());
        return $result;
    }

    protected static function checkPostgres(#[\SensitiveParameter] string $dsn): array {
        $result = [null, null, null];  // conn, tables, relay
        $current = 0;
        try {
            $pg = new DB\Pg($dsn);
            if ($pg->executeParams('select 1')->count() !== 1) {
                $result[$current] = "basic select operation failed";
                return $result;
            }

            ++$current;
            if ($pg->executeParams('select count(*) from privilege_group')->count() < 1) {
                $result[$current] = "tables not initialized";
            }

            ++$current;
            if ($pg->executeParams('select count(*) from relay.permissions')->count() < 1) {
                $result[$current] = "relay tables not working";
            }
        } catch (\Exception $e) {
            $result[$current] = $e->getMessage();
        }
        return $result;
    }
}
